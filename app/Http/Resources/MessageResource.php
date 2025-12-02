<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text' => $this->content ?? '[Audio Message]',
            'sender' => $this->sender === 'ai' ? 'bot' : 'user',
            'created_at' => $this->created_at->toIso8601String(),
            'audio_url' => $this->audio_path ? asset('storage/' . $this->audio_path) : null,
        ];
    }
}
