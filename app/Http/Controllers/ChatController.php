<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Services\ChatService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    use ApiResponse;

    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'nullable|string',
            'audio' => 'nullable|file|mimes:mp3,wav,m4a,mp4,ogg|max:10240', 
        ]);

        if (!$request->message && !$request->hasFile('audio')) {
            return $this->errorResponse('Please provide a message text or audio file.', 422);
        }

        try {
            $response = $this->chatService->handleUserMessageForUser(
                $request->user()->id,
                $request->message,
                $request->file('audio') 
            );

            return $this->successResponse(
                new MessageResource($response), 
                'Message sent'
            );

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function history(Request $request)
    {
        $limit = $request->query('limit', 50);

        $messages = $this->chatService->getHistoryWithLimit(
            $request->user()->id, 
            $limit
        );
        return $this->successResponse(MessageResource::collection($messages));
    }
}
