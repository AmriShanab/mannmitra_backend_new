<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'anonymous_id' => $this->anonymous_id, // [cite: 109]
            'role' => $this->role,
            'language' => $this->language,
            'joined_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}