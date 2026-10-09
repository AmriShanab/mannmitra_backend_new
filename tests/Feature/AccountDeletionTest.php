<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'anonymous'): User
    {
        $u = User::create([
            'name' => 'Real Name',
            'email' => $role . '@example.com',
            'password' => 'secret-pass',
            'anonymous_id' => (string) \Illuminate\Support\Str::uuid(),
            'role' => $role,
            'language' => 'en',
            'fcmToken' => 'fcm-' . $role,
            'is_paid' => true,
        ]);
        return $u;
    }

    private function seedUserData(User $u): void
    {
        $now = now();
        $sessionId = DB::table('session')->insertGetId(['user_id' => $u->id, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('messages')->insert(['session_id' => $sessionId, 'sender' => 'user', 'content' => 'private chat', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('crisis_alerts')->insert(['session_id' => $sessionId, 'trigger_keyword' => 'x', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('mood_entries')->insert(['user_id' => $u->id, 'primary_mood' => 'sad', 'note' => 'private mood', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('journal_entries')->insert(['user_id' => $u->id, 'content' => 'private journal', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('journal_entries')->insert(['user_id' => $u->id, 'content' => 'old journal', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => $now]);
        DB::table('tickets')->insert(['ticket_id' => 'TKT-1', 'user_id' => $u->id, 'subject' => 'my secret problem', 'status' => 'in_progress', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('lister_messages')->insert(['ticket_id' => 'TKT-1', 'sender_id' => $u->id, 'message' => 'private listener msg', 'created_at' => $now, 'updated_at' => $now]);
        $ticketRow = DB::table('tickets')->where('ticket_id', 'TKT-1')->first();
        DB::table('payments')->insert(['user_id' => $u->id, 'ticket_id' => $ticketRow->id, 'transaction_id' => 'pay_1', 'amount' => 99, 'status' => 'success', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('subscriptions')->insert(['user_id' => $u->id, 'plan_type' => 'monthly', 'transaction_id' => 'sub_1', 'amount' => 99, 'starts_at' => $now, 'expires_at' => $now->copy()->addMonth(), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('appointments')->insert(['appointment_id' => 'APT-1', 'user_id' => $u->id, 'scheduled_at' => $now->copy()->addDay(), 'status' => 'confirmed', 'notes' => 'private note', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('notifications')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'x', 'notifiable_type' => User::class, 'notifiable_id' => $u->id, 'data' => '{}', 'created_at' => $now, 'updated_at' => $now]);
    }

    public function test_it_erases_personal_data_and_keeps_payment_records(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser('other');
        $this->seedUserData($user);
        $user->createToken('t1');
        $user->createToken('t2');

        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/account', ['confirm' => 'DELETE'])
            ->assertOk()
            ->assertJson(['status' => true]);

        foreach (['session', 'mood_entries', 'journal_entries', 'lister_messages'] as $table) {
            $this->assertSame(0, DB::table($table)->where($table === 'lister_messages' ? 'sender_id' : 'user_id', $user->id)->count(), "$table not cleared");
        }
        $this->assertSame(0, DB::table('messages')->count());
        $this->assertSame(0, DB::table('crisis_alerts')->count());
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());

        // Kept (financial records), but anonymised / stripped
        $this->assertSame(1, DB::table('payments')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('subscriptions')->where('user_id', $user->id)->count());
        $this->assertSame('closed', DB::table('tickets')->where('ticket_id', 'TKT-1')->value('status'));
        $this->assertSame('[deleted]', DB::table('tickets')->where('ticket_id', 'TKT-1')->value('subject'));
        $apt = DB::table('appointments')->where('appointment_id', 'APT-1')->first();
        $this->assertSame('cancelled', $apt->status);
        $this->assertNull($apt->notes);

        $fresh = $user->fresh();
        $this->assertSame('Deleted User', $fresh->name);
        $this->assertNull($fresh->email);
        $this->assertNull($fresh->password);
        $this->assertNull($fresh->fcmToken);
        $this->assertSame('deleted', $fresh->role);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertFalse((bool) $fresh->is_paid);

        // Another user is untouched
        $this->assertSame('other@example.com', $other->fresh()->email);
    }

    public function test_requires_explicit_confirmation(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/account')->assertStatus(422);
        $this->deleteJson('/api/v1/account', ['confirm' => 'yes'])->assertStatus(422);
        $this->assertSame('Real Name', $user->fresh()->name);
    }

    public function test_staff_accounts_cannot_self_delete(): void
    {
        foreach (['admin', 'listener', 'doctor'] as $role) {
            $staff = $this->makeUser($role);
            Sanctum::actingAs($staff);
            $this->deleteJson('/api/v1/account', ['confirm' => 'DELETE'])->assertStatus(403);
            $this->assertSame('Real Name', $staff->fresh()->name);
        }
    }

    public function test_guests_are_rejected(): void
    {
        $this->deleteJson('/api/v1/account', ['confirm' => 'DELETE'])->assertStatus(401);
    }

    public function test_public_deletion_page_loads(): void
    {
        $this->get('/delete-account')->assertOk()->assertSee('Delete my account');
    }
}
