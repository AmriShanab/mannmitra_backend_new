<?php
namespace App\Services;
use App\Interfaces\PaymentGatewayInterface;

class MockPaymentGateway implements PaymentGatewayInterface {
    public function createOrder($amount, $currency) {
        return ['id' => 'order_mock_' . time()]; 
    }
    public function verifyPayment($attributes) {
        return true; 
    }
}