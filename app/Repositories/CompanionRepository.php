<?php

namespace App\Repositories;

use App\Models\Session;
use App\Models\Message;
use App\Models\MoodEntry;
use App\Models\JournalEntry;
use App\Models\CrisisAlert;

class CompanionRepository
{
    public function getActiveSession($userId)
    {
        return Session::firstOrCreate(
            ['user_id' => $userId, 'status' => 'active'],
            ['type' => 'text']
        );
    }

    public function createMoodEntry($userId, $score, $note)
    {
        return MoodEntry::create([
            'user_id' => $userId,
            'primary_mood' => $score,
            'note' => $note,
        ]);
    }

    public function createJournalEntry($userId, $content, $audioPath = null, $aiReflection = null)
    {
        return JournalEntry::create([
            'user_id' => $userId,
            'content' => $content,
            'audio_path' => $audioPath,
            'ai_reflection' => $aiReflection,
        ]);
    }

    public function createCrisisAlert($sessionId, $keyword)
    {
        return CrisisAlert::create([
            'session_id' => $sessionId,
            'trigger_keyword' => $keyword,
            'severity' => 'high',
            'status' => 'pending'
        ]);
    }

    public function createMessage($sessionId, $sender, $type, $content, $audioPath = null)
    {
        return Message::create([
            'session_id' => $sessionId,
            'sender' => $sender,
            'type' => $type,
            'content' => $content,
            'audio_path' => $audioPath,
        ]);
    }

    public function getTotalUserMessages($userId)
    {
        return Message::whereHas('session', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->count();
    }

    public function getRecentMessages($sessionId, $limit = 10)
    {
        return Message::where('session_id', $sessionId)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->reverse()
            ->map(function ($msg) {
                return ucfirst($msg->sender) . ": " . $msg->content;
            })->implode("\n");
    }

    public function getRecentMoods($userId, $days = 7)
    {
        $moods = MoodEntry::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($mood) {
                return "Date: {$mood->created_at->format('Y-m-d')}, Score: {$mood->primary_mood}/10";
            })->implode(" | ");
            
        return empty($moods) ? "No recent mood data." : $moods;
    }

    public function getRecentJournals($userId, $limit = 3)
    {
        $journals = JournalEntry::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($journal) {
                return "Date: {$journal->created_at->format('Y-m-d')}, Content: {$journal->content}";
            })->implode(" | ");
            
        return empty($journals) ? "No recent journal entries." : $journals;
    }

    public function flagLatestMessageAsCrisis($sessionId)
    {
        $message = Message::where('session_id', $sessionId)->orderBy('id', 'desc')->first();
        if ($message) {
            $message->update(['is_crisis' => true]);
        }
    }
}