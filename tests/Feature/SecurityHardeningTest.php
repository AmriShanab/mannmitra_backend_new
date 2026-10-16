<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Appointment;
use App\Models\Tickets;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pre-release audit: staff-only endpoints, payment replay, accept races,
 * the WhatsApp webhook secret, login throttling and token exposure.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(PaymentGatewayInterface::class, new class implements PaymentGatewayInterface {
            public function createOrder($amount, $currency, array $notes = []) { return ['id' => 'order_' . Str::random(6), 'amount' => $amount, 'currency' => 'INR']; }
            public function verifyPayment($attributes) { return true; }
            public function fetchOrder(string $orderId) { return []; }
            public function refundPayment(string $paymentId, array $notes = []) { return ['id' => 'rfnd_1', 'status' => 'processed']; }
        });
    }

    private function user(string $role = 'anonymous'): User
    {
        return User::create([
            'name' => $role, 'email' => $role . Str::random(4) . '@example.com', 'password' => 'secret-pass',
            'anonymous_id' => (string) Str::uuid(), 'role' => $role, 'language' => 'en',
        ]);
    }

    private function appointment(User $patient, string $status, ?User $doctor = null): Appointment
    {
        return Appointment::create([
            'appointment_id' => 'APT-' . strtoupper(Str::random(8)),
            'user_id' => $patient->id,
            'psychiatrist_id' => $doctor?->id,
            'scheduled_at' => now('Asia/Kolkata')->addDay()->format('Y-m-d H:i:s'),
            'mode' => 'video',
            'notes' => 'private patient note',
            'status' => $status,
            'meeting_link' => 'MEET-' . strtoupper(Str::random(8)),
            'razorpay_order_id' => 'order_1',
        ]);
    }

    private function ticket(User $owner, string $status = 'open'): Tickets
    {
        return Tickets::create([
            'ticket_id' => 'TKT-' . strtoupper(Str::random(10)),
            'user_id' => $owner->id,
            'subject' => 'private subject',
            'status' => $status,
            'payment_verified' => $status !== 'pending_payment',
            'razorpay_order_id' => 'order_t',
        ]);
    }

    public function test_anonymous_users_cannot_use_psychiatrist_endpoints(): void
    {
        $patient = $this->user();
        $apt = $this->appointment($patient, 'pending');

        Sanctum::actingAs($this->user());

        $this->getJson('/api/v1/appointments/pending')->assertForbidden();
        $this->getJson('/api/v1/appointments/my-schedule')->assertForbidden();
        $this->postJson("/api/v1/appointments/{$apt->appointment_id}/accept")->assertForbidden();
        $this->postJson("/api/v1/appointments/{$apt->appointment_id}/cancel-decision", ['approve' => true])->assertForbidden();

        $this->assertNull($apt->fresh()->psychiatrist_id);
    }

    public function test_doctors_can_list_and_accept_and_only_one_accept_wins(): void
    {
        $apt = $this->appointment($this->user(), 'pending');
        $first = $this->user('doctor');
        $second = $this->user('doctor');

        Sanctum::actingAs($first);
        $this->getJson('/api/v1/appointments/pending')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/appointments/{$apt->appointment_id}/accept")->assertOk();

        Sanctum::actingAs($second);
        $this->postJson("/api/v1/appointments/{$apt->appointment_id}/accept")->assertStatus(400);

        $this->assertSame($first->id, (int) $apt->fresh()->psychiatrist_id);
    }

    public function test_anonymous_users_cannot_use_listener_endpoints(): void
    {
        $ticket = $this->ticket($this->user());

        Sanctum::actingAs($this->user());
        $this->getJson('/api/v1/tickets/pool')->assertForbidden();
        $this->postJson("/api/v1/tickets/{$ticket->id}/accept")->assertForbidden();

        $this->assertNull($ticket->fresh()->listener_id);
    }

    public function test_listeners_accept_paid_tickets_once(): void
    {
        $ticket = $this->ticket($this->user());
        $unpaid = $this->ticket($this->user(), 'pending_payment');

        Sanctum::actingAs($this->user('listener'));
        $this->getJson('/api/v1/tickets/pool')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/tickets/{$ticket->id}/accept")->assertOk();
        $this->postJson("/api/v1/tickets/{$unpaid->id}/accept")->assertStatus(400);

        Sanctum::actingAs($this->user('listener'));
        $this->postJson("/api/v1/tickets/{$ticket->id}/accept")->assertStatus(400);
    }

    public function test_payment_confirmation_cannot_revive_a_cancelled_appointment(): void
    {
        // Starts paid ('pending'): SQLite lacks the MySQL-only 'pending_payment' enum value.
        $patient = $this->user();
        $apt = $this->appointment($patient, 'pending');
        $apt->update(['razorpay_payment_id' => 'pay_1']);
        Sanctum::actingAs($patient);

        $confirm = fn (string $paymentId) => $this->postJson('/api/v1/appointments/pay-confirm', [
            'appointment_id' => $apt->appointment_id, 'payment_id' => $paymentId, 'signature' => 'sig',
        ]);

        $confirm('pay_1')->assertOk(); // the app retrying the same payment is harmless
        $this->assertSame('pending', $apt->fresh()->status);

        $this->postJson("/api/v1/appointments/{$apt->appointment_id}/cancel-request")->assertOk();
        $this->assertSame('cancelled', $apt->fresh()->status);

        // Replaying the old payment, or another one, must not revive the refunded booking.
        $confirm('pay_1');
        $this->assertSame('cancelled', $apt->fresh()->status);
        $confirm('pay_2')->assertStatus(409);
        $this->assertSame('cancelled', $apt->fresh()->status);
    }

    public function test_hanging_up_does_not_complete_an_unassigned_appointment(): void
    {
        $patient = $this->user();
        $apt = $this->appointment($patient, 'pending');
        Sanctum::actingAs($patient);

        $this->postJson('/api/v1/appointments/close', ['meeting_link' => $apt->meeting_link])->assertOk();
        $this->assertSame('pending', $apt->fresh()->status);
    }

    public function test_appointment_mode_is_validated(): void
    {
        Sanctum::actingAs($this->user());
        $this->postJson('/api/v1/appointments/create', [
            'scheduled_at' => now()->addDay()->toDateTimeString(), 'mode' => '<script>',
        ])->assertStatus(422);
    }

    public function test_whatsapp_webhook_requires_the_shared_secret(): void
    {
        $body = ['messages' => [['chat_id' => '911234567890@s.whatsapp.net', 'type' => 'text', 'text' => ['body' => 'hi']]]];

        config(['services.whapi.webhook_secret' => null]);
        $this->postJson('/api/v1/whatsapp/webhook', $body)->assertStatus(503);

        config(['services.whapi.webhook_secret' => 'right-secret']);
        $this->postJson('/api/v1/whatsapp/webhook?token=wrong', $body)->assertStatus(401);
        $this->postJson('/api/v1/whatsapp/webhook', $body)->assertStatus(401);

        // Correct secret, but nothing to answer: ignored without calling any external service.
        $this->postJson('/api/v1/whatsapp/webhook?token=right-secret', ['messages' => []])
            ->assertOk()->assertJson(['status' => 'ignored']);
    }

    public function test_the_test_admin_backdoor_is_gone(): void
    {
        $this->get('/test-admin')->assertNotFound();
        $this->assertGuest();
    }

    public function test_staff_api_login_is_throttled(): void
    {
        $this->user('listener');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'nope'])->assertStatus(401);
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'nope'])->assertStatus(429);
    }

    public function test_guest_login_never_returns_the_device_token_and_accepts_voice_sessions(): void
    {
        $res = $this->postJson('/api/v1/sessions/start', [
            'fcmToken' => 'device-token-123', 'languageCode' => 'hi', 'session_type' => 'voice',
        ])->assertOk();

        $this->assertStringNotContainsString('device-token-123', $res->getContent());
        $this->assertSame('voice', $res->json('data.session.type'));
    }
}
