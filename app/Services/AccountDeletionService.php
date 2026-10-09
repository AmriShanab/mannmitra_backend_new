<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CrisisAlert;
use App\Models\JournalEntry;
use App\Models\ListerMessages;
use App\Models\Message;
use App\Models\MoodEntry;
use App\Models\Session;
use App\Models\Tickets;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Permanently erases a user's personal data (in-app "Delete account").
 *
 * Removed: chats, crisis alerts, mood and journal entries, listener-chat messages,
 * notifications, API tokens, push token and all profile details.
 * Kept (anonymised, no personal data): payments, subscriptions, and appointment/ticket
 * rows, because payment records must be retained under Indian tax/accounting law.
 */
class AccountDeletionService
{
    /** Roles that may NOT self-delete; staff accounts are removed by an administrator. */
    public const PROTECTED_ROLES = ['admin', 'listener', 'doctor'];

    public function canDelete(User $user): bool
    {
        return !in_array($user->role, self::PROTECTED_ROLES, true);
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $userId = $user->id;

            // Listener-chat tickets: erase the conversation, close the ticket, blank the subject.
            $ticketIds = Tickets::where('user_id', $userId)->pluck('ticket_id');
            if ($ticketIds->isNotEmpty()) {
                ListerMessages::whereIn('ticket_id', $ticketIds)->delete();
            }
            Tickets::where('user_id', $userId)->update([
                'subject' => '[deleted]',
                'status'  => 'closed',
            ]);

            // Doctor appointments: cancel anything still upcoming, drop free-text notes.
            Appointment::where('user_id', $userId)
                ->whereIn('status', ['pending_payment', 'pending', 'confirmed'])
                ->update(['status' => 'cancelled']);
            Appointment::where('user_id', $userId)->update(['notes' => null]);

            // AI chat sessions with their messages and crisis alerts.
            $sessionIds = Session::where('user_id', $userId)->pluck('id');
            if ($sessionIds->isNotEmpty()) {
                Message::whereIn('session_id', $sessionIds)->delete();
                CrisisAlert::whereIn('session_id', $sessionIds)->delete();
                Session::whereIn('id', $sessionIds)->delete();
            }

            MoodEntry::where('user_id', $userId)->delete();
            JournalEntry::withTrashed()->where('user_id', $userId)->forceDelete();

            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $userId)
                ->delete();

            // Revoke every API token (all devices).
            $user->tokens()->delete();

            // Anonymise the account row.
            $user->forceFill([
                'name'           => 'Deleted User',
                'email'          => null,
                'password'       => null,
                'remember_token' => null,
                'anonymous_id'   => (string) Str::uuid(),
                'fcmToken'       => null,
                'role'           => 'deleted',
                'is_paid'        => false,
                'last_active_at' => null,
                'deleted_at'     => now(),
            ])->save();
        });
    }
}
