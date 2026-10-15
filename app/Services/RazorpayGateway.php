<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Razorpay\Api\Api;

class RazorpayGateway implements PaymentGatewayInterface
{
    protected Api $api;

    public function __construct()
    {
        $this->api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
    }

    public function createOrder($amount, $currency = 'INR', array $notes = [])
    {
        $order = $this->api->order->create([
            'amount' => (int) $amount,
            'currency' => $currency,
            'receipt' => 'rcpt_' . Str::lower(Str::random(12)),
            'notes' => $notes,
        ]);

        return [
            'id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'key' => config('services.razorpay.key'),
        ];
    }

    public function verifyPayment($attributes)
    {
        if (empty($attributes['order_id']) || empty($attributes['payment_id']) || empty($attributes['signature'])) {
            return false;
        }

        try {
            $this->api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $attributes['order_id'],
                'razorpay_payment_id' => $attributes['payment_id'],
                'razorpay_signature' => $attributes['signature'],
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Razorpay signature verification failed: ' . $e->getMessage());
            return false;
        }
    }

    public function refundPayment(string $paymentId, array $notes = [])
    {
        // Posted straight to /payments/{id}/refund: the SDK's Payment::refund() helper sends the
        // request to /refunds instead, which Razorpay rejects with "invalid request sent".
        $refund = (new \Razorpay\Api\Request())->request(
            'POST',
            'payments/' . rawurlencode($paymentId) . '/refund',
            ['speed' => 'normal', 'notes' => $notes]
        );
        $refund = is_array($refund) ? $refund : (array) $refund;

        return ['id' => $refund['id'], 'status' => $refund['status'] ?? 'pending'];
    }

    public function fetchOrder(string $orderId)
    {
        $order = $this->api->order->fetch($orderId)->toArray();

        return [
            'id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'status' => $order['status'],
            'notes' => is_array($order['notes'] ?? null) ? $order['notes'] : [],
        ];
    }
}
