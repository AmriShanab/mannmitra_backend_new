<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Models\Message;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateWeeklyJournals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'companion:generate-weekly-journals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyzes user chat history from the past week and generates a summary journal entry.';
    /**
     * Execute the console command.
     */
    protected $openAi;

    public function __construct(AIService $openAi)
    {
        parent::__construct();
        $this->openAi = $openAi;
    }
    public function handle()
    {
        $this->info('Starting weekly journal generation process...');

        $oneWeekAgo = now()->subDays(7);

        $users = User::whereHas('sessions.messages', function ($query) use ($oneWeekAgo) {
            $query->where('sender', 'user')->where('created_at', '>=', $oneWeekAgo);
        })->get();

        $this->info('Found ' . $users->count() . ' users with activity in the past week.');

        foreach ($users as $key => $user) {
            try {
                // Get all user messages from the past week
                $messages = Message::whereHas('session', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                    ->where('sender', 'user')
                    ->where('created_at', '>=', $oneWeekAgo)
                    ->orderBy('created_at', 'asc')
                    ->pluck('content')
                    ->implode("\n");

                // Skip if there's barely any text to summarize
                if (strlen($messages) < 50) {
                    $this->line("Skipping User ID {$user->id} - not enough data.");
                    continue;
                }

                $language = ($user->language === 'hi') ? 'Hindi (Latin script/Hinglish)' : 'English';

                $systemPrompt = "
                    You are an analytical AI tasked with summarizing a user's weekly chat logs with their AI companion into a single, cohesive journal entry.
                    
                    TASK:
                    Write a brief (3-4 sentences), first-person reflection summarizing the user's week based on their chat history. It should read as if the user wrote it themselves in a diary (e.g., 'This week, I felt...'). 
                    Focus on their primary emotions, challenges, and any positive moments they shared.
                    
                    RULES:
                    1. Write entirely in {$language}.
                    2. DO NOT include AI responses or mention 'Mann Mitra' or 'the AI'. Just focus on the user's feelings.
                    3. Return ONLY the JSON object below.
                    
                    FORMAT:
                    {
                        \"journal_summary\": \"<The 3-4 sentence first-person reflection>\"
                    }
                ";

                $userInstruction = "User's Chat Logs (Past 7 Days):\n\n" . $messages;

                $aiData = $this->openAi->getChatCompletion($systemPrompt, $userInstruction);

                if (!empty($aiData['journal_summary'])) {
                    JournalEntry::create([
                        'user_id' => $user->id,
                        'title' => 'Weekly Reflection',
                        'content' => $aiData['journal_summary'],
                        'ai_reflection' => 'Auto-generated weekly summary from chat history.',
                    ]);
                    $this->info("Successfully generated journal for User ID {$user->id}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to generate weekly journal for User ID {$user->id}: " . $e->getMessage());
                $this->error("Error on User ID {$user->id}. Check logs.");
            }
        }

        $this->info('Weekly journal generation process completed.');
    }
}
