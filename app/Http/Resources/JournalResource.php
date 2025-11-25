<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'content' => $this->content,
            'mood_snapshot' => $this->moodSnapshot,
            'tags' => $this->tags ?? [],
            'ai_reflection' => $this->ai_reflection,
            'has_audio' => !empty($this->audio_path),
            'created_at' => $this->created_at->toIso8601String(),
            'human_time' => $this->created_at->diffForHumans(),
        ];
    }
}