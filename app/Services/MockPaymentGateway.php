<?php
namespace App\Services;
use App\Interfaces\PaymentGatewayInterface;

// Dev-only stand-in. Not bound by default; see AppServiceProvider.
class MockPaymentGateway implements PaymentGatewayInterface {
    public function createOrder($amount, $currency, array $notes = []) {
        return ['id' => 'order_mock_' . time(), 'amount' => $amount, 'currency' => $currency, 'notes' => $notes];
    }
    public function verifyPayment($attributes) {
        return true;
    }
    public function fetchOrder(string $orderId) {
        return ['id' => $orderId, 'amount' => 0, 'currency' => 'INR', 'status' => 'paid', 'notes' => []];
    }
}
