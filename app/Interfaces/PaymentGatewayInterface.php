<?php

namespace App\Interfaces;

interface PaymentGatewayInterface
{
    public function createOrder($amount, $currency);
    public function verifyPayment($attributes);
}