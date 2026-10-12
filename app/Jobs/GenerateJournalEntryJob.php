<?php

namespace App\Jobs;

use App\Models\JournalEntry;
use App\Models\Message;
use App\Models\MoodEntry;
use App\Models\User;
use App\Services\AIService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use App\Support\Language;

/**
 * Writes one auto-generated journal entry (daily or weekly) for one user from their chats.
 * Dispatched per user by the scheduled commands so a slow or failing user never blocks the rest.
 */
class GenerateJournalEntryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const DAILY = 'daily';
    public const WEEKLY = 'weekly';

    public $tries = 3;
    public $backoff = [30, 120];

    /**
     * @param string $date the day being summarised (Y-m-d); for weekly, the last day of the week
     */
    public function __construct(public User $user, public string $period, public string $date)
    {
    }

    public function handle(AIService $ai): void
    {
        // So ai_usage_logs attributes this call to the user, not to "nobody".
        Auth::setUser($this->user);

        $daily = $this->period === self::DAILY;
        $day = Carbon::parse($this->date);

        $messages = Message::whereHas('session', fn($q) => $q->where('user_id', $this->user->id))
            ->where('sender', 'user')
            ->when(
                $daily,
                fn($q) => $q->whereDate('created_at', $day->toDateString()),
                fn($q) => $q->where('created_at', '>=', $day->copy()->subDays(7)->startOfDay())
                    ->where('created_at', '<=', $day->copy()->endOfDay())
            )
            ->orderBy('created_at')
            ->pluck('content')
            ->implode("\n");

        // Barely any text: nothing worth summarising (weekly only; a daily user has sent something).
        if (!$daily && strlen($messages) < 50) {
            return;
        }

        $todayMood = $daily
            ? MoodEntry::where('user_id', $this->user->id)->whereDate('created_at', $day->toDateString())->latest('id')->first()
            : null;
        $moodText = $todayMood ? "Today's mood: {$todayMood->primary_mood}/10" : 'No mood logged today.';

        $language = Language::promptName($this->user->language);
        $span = $daily ? "day" : "week";
        $opening = $daily ? 'Today, I felt...' : 'This week, I felt...';
        $sentence = $daily ? "the user's day based on their text messages and mood score" : "the user's week based on their chat history";

        $systemPrompt = "
            You are an analytical AI tasked with summarizing a user's {$span}ly chat logs with their AI companion into a single, cohesive journal entry.

            TASK:
            Write a brief (3-4 sentences), first-person reflection summarizing {$sentence}. It should read as if the user wrote it themselves in their personal diary (e.g., '{$opening}').
            Focus on their primary emotions, challenges, and any positive moments they shared.

            RULES:
            1. Write entirely in {$language}.
            2. DO NOT include AI responses or mention 'Mann Mitra' or 'the AI'. Just focus on the user's feelings and events.
            3. Return ONLY the JSON object below.

            FORMAT:
            {
                \"journal_summary\": \"<The 3-4 sentence first-person reflection>\"
            }
        ";

        $instruction = $daily
            ? "User's Mood Today: {$moodText}\n\nUser's Text Chats Today:\n{$messages}"
            : "User's Chat Logs (Past 7 Days):\n\n{$messages}";

        $data = $ai->getChatCompletion($systemPrompt, $instruction, "journal.{$this->period}");

        if (!empty($data['journal_summary'])) {
            JournalEntry::create([
                'user_id' => $this->user->id,
                'title' => $daily ? 'Daily Reflection' : 'Weekly Reflection',
                'content' => $data['journal_summary'],
                'mood_snapshot' => $todayMood?->primary_mood,
                'ai_reflection' => $daily
                    ? 'Auto-generated daily summary from text chats.'
                    : 'Auto-generated weekly summary from chat history.',
            ]);
        }
    }
}
