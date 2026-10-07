<?php

namespace App\Interfaces;

interface PaymentGatewayInterface
{
    /**
     * Create a gateway order. $amount is in the smallest currency unit (paise).
     * Returns at least ['id' => ..., 'amount' => ..., 'currency' => ...].
     */
    public function createOrder($amount, $currency, array $notes = []);

    /**
     * $attributes: order_id, payment_id, signature.
     */
    public function verifyPayment($attributes);

    /**
     * Fetch an existing order (amount, notes, status).
     */
    public function fetchOrder(string $orderId);
}
