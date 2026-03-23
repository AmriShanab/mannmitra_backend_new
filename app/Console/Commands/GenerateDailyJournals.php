<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Models\Message;
use App\Models\MoodEntry;
use App\Models\User;
use App\Services\AIService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateDailyJournals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'companion:generate-daily-journals';
    protected $openAi;

    public function __construct(AIService $openAi)
    {
        parent::__construct();
        $this->openAi = $openAi;
    }

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze today\'s user messages, moods and appointments to generate journal entries for the day';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting daily journal generation...');
        $today = Carbon::today()->toDateString();

        $users = User::whereHas('sessions.messages', function ($query) use ($today) {
            $query->where('sender', 'user')->whereDate('created_at', $today);
        })->get();

        $this->info('Found ' . $users->count() . ' users with messages today.');

        foreach ($users as $user) {
            try {
                $todayMood = MoodEntry::where('user_id', $user->id)
                    ->whereDate('created_at', $today)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $moodText = $todayMood ? "Today's mood: " . $todayMood->mood : "No mood logged today.";

                $messages = Message::whereHas('session', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                    ->where('sender', 'user')
                    // ->where('type', '!=', 'audio') // Excludes voice records!
                    ->whereDate('created_at', $today)
                    ->orderBy('created_at', 'asc')
                    ->pluck('content')
                    ->implode("\n");


                $language = ($user->language === 'hi') ? 'Hindi (Latin script/Hinglish)' : 'English';

                $systemPrompt = "
                    You are an analytical AI tasked with summarizing a user's daily chat logs with their AI companion into a single, cohesive journal entry.
                    
                    TASK:
                    Write a brief (3-4 sentences), first-person reflection summarizing the user's day based on their text messages and mood score. It should read as if the user wrote it themselves in their personal diary (e.g., 'Today, I felt...'). 
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

                $userInstruction = "User's Mood Today: " . $moodText . "\n\nUser's Text Chats Today:\n" . $messages;

                $aiData = $this->openAi->getChatCompletion($systemPrompt, $userInstruction);

             if (!empty($aiData['journal_summary'])) {
                    JournalEntry::create([
                        'user_id' => $user->id,
                        'title' => 'Daily Reflection',
                        'content' => $aiData['journal_summary'],
                        'mood_snapshot' => $todayMood ? $todayMood->primary_mood : null, // <-- ADDED THIS LINE
                        'ai_reflection' => 'Auto-generated daily summary from text chats.',
                    ]);
                    $this->info("Successfully generated daily journal for User ID {$user->id}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to generate daily journal for User ID {$user->id}: " . $e->getMessage());
                $this->error("Error on User ID {$user->id}. Check logs.");
            }
            
        }
        $this->info('Daily journal generation process completed.');
    }
}
