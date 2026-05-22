<?php
namespace App\Services;
use App\Interfaces\PaymentGatewayInterface;

class MockPaymentGateway implements PaymentGatewayInterface {
    public function createOrder($amount, $currency) {
        return ['id' => 'order_mock_' . time()]; // Fake Order ID
    }
    public function verifyPayment($attributes) {
        return true; // Always return true for testing!
    }
}