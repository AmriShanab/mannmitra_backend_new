<?php

namespace App\Console\Commands;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Console\Command;

class TestRefund extends Command
{
    protected $signature = 'app:test-refund {payment_id : Razorpay payment id, e.g. pay_XXXX}
        {--raw : Debug: try several request shapes directly and print the raw replies (stops at the first success)}';
    protected $description = 'Fully refund one Razorpay payment (use with TEST keys to check refunds work)';

    public function handle(PaymentGatewayInterface $gateway): int
    {
        $key = (string) config('services.razorpay.key');
        $this->line('Mode: ' . (str_starts_with($key, 'rzp_test_') ? 'TEST' : 'LIVE'));

        if (!str_starts_with($key, 'rzp_test_') && !$this->confirm('These are LIVE keys. This refunds real money. Continue?')) {
            return self::SUCCESS;
        }

        if ($this->option('raw')) {
            return $this->raw($this->argument('payment_id'));
        }

        try {
            $refund = $gateway->refundPayment($this->argument('payment_id'), ['reason' => 'manual_test']);
            $this->info("Refund created: {$refund['id']} (status: {$refund['status']})");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Refund failed: ' . $e->getMessage());
            $this->line('Type: ' . get_class($e) . ' | code: ' . $e->getCode()
                . (method_exists($e, 'getField') && $e->getField() ? ' | field: ' . $e->getField() : ''));
            return self::FAILURE;
        }
    }

    private function raw(string $paymentId): int
    {
        $url = 'https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId) . '/refund';
        $auth = config('services.razorpay.key') . ':' . config('services.razorpay.secret');
        $amount = (int) (new \Razorpay\Api\Api(config('services.razorpay.key'), config('services.razorpay.secret')))
            ->payment->fetch($paymentId)->amount;

        $variants = [
            'no body' => [[], null],
            'form amount' => [[], http_build_query(['amount' => $amount])],
            'json amount' => [['Content-Type: application/json'], json_encode(['amount' => $amount])],
        ];

        foreach ($variants as $name => [$headers, $body]) {
            $c = curl_init($url);
            curl_setopt_array($c, [
                CURLOPT_USERPWD => $auth, CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body ?? '',
            ]);
            $reply = curl_exec($c);
            $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
            $this->line("[$name] HTTP $code " . preg_replace('/\s+/', ' ', (string) $reply));
            if ($code >= 200 && $code < 300) {
                return self::SUCCESS;
            }
        }
        return self::FAILURE;
    }
}
