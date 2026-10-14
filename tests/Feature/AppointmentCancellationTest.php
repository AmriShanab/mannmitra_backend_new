<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentCancellationTest extends TestCase
{
    use RefreshDatabase;

    private $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new class implements PaymentGatewayInterface {
            public array $refunded = [];
            public bool $fail = false;
            public function createOrder($amount, $currency, array $notes = []) { return []; }
            public function verifyPayment($attributes) { return true; }
            public function fetchOrder(string $orderId) { return []; }
            public function refundPayment(string $paymentId, array $notes = [])
            {
                if ($this->fail) {
                    throw new \RuntimeException('gateway down');
                }
                $this->refunded[] = $paymentId;
                return ['id' => 'rfnd_1', 'status' => 'pending'];
            }
        };
        $this->app->instance(PaymentGatewayInterface::class, $this->gateway);
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => $role, 'email' => $role . '@example.com', 'password' => 'secret-pass',
            'anonymous_id' => (string) \Illuminate\Support\Str::uuid(), 'role' => $role, 'language' => 'en',
        ]);
    }

    private function appointment(User $patient, ?User $doctor, string $status, string $when = '+1 day'): Appointment
    {
        return Appointment::create([
            'appointment_id' => 'APT-' . strtoupper(\Illuminate\Support\Str::random(8)),
            'user_id' => $patient->id,
            'psychiatrist_id' => $doctor?->id,
            'scheduled_at' => now('Asia/Kolkata')->modify($when)->format('Y-m-d H:i:s'),
            'mode' => 'audio',
            'status' => $status,
            'meeting_link' => 'MEET-' . strtoupper(\Illuminate\Support\Str::random(8)),
            'razorpay_order_id' => 'order_1',
            'razorpay_payment_id' => 'pay_1',
        ]);
    }

    public function test_confirmed_appointment_needs_psychiatrist_approval_then_refunds(): void
    {
        $patient = $this->user('anonymous');
        $doctor = $this->user('doctor');
        $apt = $this->appointment($patient, $doctor, 'confirmed');

        Sanctum::actingAs($patient);
        $this->postJson("/api/v1/appointments/{$apt->appointment_id}/cancel-request")
            ->assertOk()->assertJsonPath('result', 'requested');
        $this->assertSame('confirmed', $apt->fresh()->status);
        $this->assertSame([], $this->gateway->refunded);

        Sanctum::actingAs($doctor);
        $this->postJson("/api/v1/appointments/{$apt->appointment_id}/cancel-decision", ['approve' => true])->assertOk();

        $fresh = $apt->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('rfnd_1', $fresh->refund_id);
        $this->assertSame('initiated', $fresh->refund_status);
        $this->assertSame(['pay_1'], $this->gateway->refunded);
    }

    public function test_declined_request_keeps_appointment_and_does_not_refund(): void
    {
        $patient = $this->user('anonymous');
        $doctor = $this->user('doctor');
        $apt = $this->appointment($patient, $doctor, 'confirmed');
        $apt->update(['cancel_requested_at' => now()]);

        Sanctum::actingAs($doctor);
        $this->postJson("/api/v1/appointments/{$apt->appointment_id}/cancel-decision", ['approve' => false])->assertOk();

        $fresh = $apt->fresh();
        $this->assertSame('confirmed', $fresh->status);
        $this->assertNull($fresh->cancel_requested_at);
        $this->assertSame([], $this->gateway->refunded);
    }

    public function test_other_psychiatrist_cannot_decide(): void
    {
        $patient = $this->user('anonymous');
        $doctor = $this->user('doctor');
        $other = User::create([
            'name' => 'other', 'email' => 'other@example.com', 'password' => 'secret-pass',
            'anonymous_id' => (string) \Illuminate\Support\Str::uuid(), 'role' => 'doctor', 'language' => 'en',
        ]);
        $apt = $this->appointment($patient, $doctor, 'confirmed');
        $apt->update(['cancel_requested_at' => now()]);

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/appointments/{$apt->appointment_id}/cancel-decision", ['approve' => true])->assertStatus(404);
        $this->assertSame('confirmed', $apt->fresh()->status);
    }

    public function test_refund_failure_leaves_everything_unchanged(): void
    {
        $patient = $this->user('anonymous');
        $doctor = $this->user('doctor');
        $apt = $this->appointment($patient, $doctor, 'confirmed');
        $apt->update(['cancel_requested_at' => now()]);
        $this->gateway->fail = true;

        Sanctum::actingAs($doctor);
        $this->postJson("/api/v1/appointments/{$apt->appointment_id}/cancel-decision", ['approve' => true])->assertStatus(502);

        $fresh = $apt->fresh();
        $this->assertSame('confirmed', $fresh->status);
        $this->assertNotNull($fresh->cancel_requested_at);
    }

    public function test_unaccepted_appointment_is_cancelled_and_refunded_directly(): void
    {
        $patient = $this->user('anonymous');
        $apt = $this->appointment($patient, null, 'pending');

        Sanctum::actingAs($patient);
        $this->postJson("/api/v1/appointments/{$apt->appointment_id}/cancel-request")
            ->assertOk()->assertJsonPath('result', 'cancelled');

        $this->assertSame('cancelled', $apt->fresh()->status);
        $this->assertSame(['pay_1'], $this->gateway->refunded);
    }

    public function test_started_appointment_or_someone_elses_cannot_be_cancelled(): void
    {
        $patient = $this->user('anonymous');
        $doctor = $this->user('doctor');
        $past = $this->appointment($patient, $doctor, 'confirmed', '-10 minutes');
        $future = $this->appointment($patient, $doctor, 'confirmed');

        Sanctum::actingAs($patient);
        $this->postJson("/api/v1/appointments/{$past->appointment_id}/cancel-request")->assertStatus(422);

        Sanctum::actingAs($this->user('listener'));
        $this->postJson("/api/v1/appointments/{$future->appointment_id}/cancel-request")->assertStatus(404);
    }
}
