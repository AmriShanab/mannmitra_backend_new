<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\Carbon;
use Exception;
use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AppointmentService
{
    public function __construct(protected PaymentGatewayInterface $paymentGateway)
    {
    }

    public function createRequest($user, $data, $razorpayOrderId)
    {
        $aptId = 'APT-' . strtoupper(Str::random(8));
        $meetingLink = 'MEET-' . strtoupper(Str::random(12));

        return Appointment::create([
            'appointment_id' => $aptId,
            'user_id' => $user->id,
            'scheduled_at' => Carbon::parse($data['scheduled_at'], 'Asia/Kolkata')->format('Y-m-d H:i:s'),
            'mode' => $data['mode'] ?? 'video',
            'notes' => $data['notes'] ?? null,
            'meeting_link' => $meetingLink,
            'status' => 'pending_payment', 
            'razorpay_order_id' => $razorpayOrderId,
            'transaction_id' => $data['transaction_id'] ?? null,
        ]);
    }

    /**
     * Marks a verified payment. Only an unpaid booking moves forward: replaying the same
     * signature later (e.g. after a cancellation + refund) must not revive the appointment.
     */
    public function processPayment($appointmentId, $paymentId)
    {
        $updated = Appointment::where('appointment_id', $appointmentId)
            ->where('status', 'pending_payment')
            ->update([
                'status' => 'pending',
                'razorpay_payment_id' => $paymentId,
                'transaction_id' => $paymentId,
            ]);

        $appointment = Appointment::where('appointment_id', $appointmentId)->firstOrFail();

        if (!$updated && $appointment->razorpay_payment_id !== $paymentId) {
            throw new Exception('This appointment has already been paid for or is no longer active.', 409);
        }

        return $appointment;
    }

    public function acceptRequest($appointmentId, $psychiatristId)
    {
        // Conditional update so two psychiatrists accepting at once cannot both win.
        $updated = Appointment::where('appointment_id', $appointmentId)
            ->where('status', 'pending')
            ->whereNull('psychiatrist_id')
            ->update([
                'psychiatrist_id' => $psychiatristId,
                'status' => 'confirmed',
            ]);

        if (!$updated) {
            throw new \Exception('The Appointment is no longer available.', 400);
        }

        return Appointment::where('appointment_id', $appointmentId)->firstOrFail();
    }

    /**
     * Patient asks to cancel. Before a psychiatrist has accepted, nobody needs to approve, so it is
     * cancelled and refunded straight away. After acceptance, the psychiatrist must allow it.
     *
     * @return array{result: string, appointment: Appointment}  result: 'cancelled' | 'requested'
     */
    public function requestCancellation(string $appointmentId, int $userId): array
    {
        return DB::transaction(function () use ($appointmentId, $userId) {
            $appointment = Appointment::where('appointment_id', $appointmentId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$appointment) {
                throw new Exception('Appointment not found.', 404);
            }
            if (!in_array($appointment->status, ['pending', 'confirmed'], true)) {
                throw new Exception('This appointment can no longer be cancelled.', 422);
            }
            if (Carbon::parse($appointment->scheduled_at, 'Asia/Kolkata')->lte(Carbon::now('Asia/Kolkata'))) {
                throw new Exception('This appointment has already started.', 422);
            }

            if ($appointment->status === 'pending') {
                $this->cancelAndRefund($appointment);
                return ['result' => 'cancelled', 'appointment' => $appointment->fresh()];
            }

            if (!$appointment->cancel_requested_at) {
                $appointment->update(['cancel_requested_at' => now()]);
            }

            return ['result' => 'requested', 'appointment' => $appointment->fresh()];
        });
    }

    /**
     * The assigned psychiatrist allows or declines a cancellation request.
     * Approving cancels the appointment and starts a full refund. If the refund call fails,
     * nothing changes, so the psychiatrist can simply try again.
     */
    public function decideCancellation(string $appointmentId, int $psychiatristId, bool $approve): Appointment
    {
        return DB::transaction(function () use ($appointmentId, $psychiatristId, $approve) {
            $appointment = Appointment::where('appointment_id', $appointmentId)
                ->where('psychiatrist_id', $psychiatristId)
                ->lockForUpdate()
                ->first();

            if (!$appointment) {
                throw new Exception('Appointment not found.', 404);
            }
            if ($appointment->status !== 'confirmed' || !$appointment->cancel_requested_at) {
                throw new Exception('There is no open cancellation request for this appointment.', 422);
            }

            if (!$approve) {
                $appointment->update(['cancel_requested_at' => null]);
                return $appointment->fresh();
            }

            $this->cancelAndRefund($appointment);
            return $appointment->fresh();
        });
    }

    /** Marks the appointment cancelled and refunds the payment in full. Throws if the refund fails. */
    private function cancelAndRefund(Appointment $appointment): void
    {
        $refund = null;

        if ($appointment->razorpay_payment_id && !$appointment->refund_id) {
            try {
                $refund = $this->paymentGateway->refundPayment($appointment->razorpay_payment_id, [
                    'appointment_id' => $appointment->appointment_id,
                    'reason' => 'appointment_cancelled',
                ]);
            } catch (\Throwable $e) {
                // Already refunded on the Razorpay side: carry on and just cancel.
                if (!str_contains(strtolower($e->getMessage()), 'fully refunded')) {
                    Log::error('Appointment refund failed', ['appointment' => $appointment->appointment_id, 'error' => $e->getMessage()]);
                    throw new Exception('The refund could not be started (' . $e->getMessage() . '). Nothing was changed.', 502);
                }
            }
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_requested_at' => null,
            'refund_id' => $refund['id'] ?? $appointment->refund_id,
            'refund_status' => $refund || $appointment->refund_id ? 'initiated' : null,
            'refund_initiated_at' => $refund ? now() : $appointment->refund_initiated_at,
        ]);
    }
}
