<?php

namespace App\Console\Commands;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Console\Command;

class TestRefund extends Command
{
    protected $signature = 'app:test-refund {payment_id : Razorpay payment id, e.g. pay_XXXX}';
    protected $description = 'Fully refund one Razorpay payment (use with TEST keys to check refunds work)';

    public function handle(PaymentGatewayInterface $gateway): int
    {
        $key = (string) config('services.razorpay.key');
        $this->line('Mode: ' . (str_starts_with($key, 'rzp_test_') ? 'TEST' : 'LIVE'));

        if (!str_starts_with($key, 'rzp_test_') && !$this->confirm('These are LIVE keys. This refunds real money. Continue?')) {
            return self::SUCCESS;
        }

        try {
            $refund = $gateway->refundPayment($this->argument('payment_id'), ['reason' => 'manual_test']);
            $this->info("Refund created: {$refund['id']} (status: {$refund['status']})");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Refund failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
