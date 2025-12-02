<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
            'session_id' => 'required|exists:session,id', 
            'message' => 'nullable|string', 
            'audio' => 'nullable|file|mimes:mp3,wav,m4a,mp4,ogg|max:10240', 
        ]);

        if (!$request->message && !$request->hasFile('audio')) {
            return $this->errorResponse('Please provide a message or audio file.', 422);
        }

        try {
            $response = $this->chatService->handleUserMessage(
                $request->session_id,
                $request->message,
                $request->file('audio') 
            );

            if ($response->audio_path) {
                $response->audio_url = asset('storage/' . $response->audio_path);
            }

            return $this->successResponse($response, 'Message processed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function history(Request $request)
    {
        $request->validate(['session_id' => 'required|exists:session,id']);

        $messages = $this->chatService->getMessages($request->session_id);

        return $this->successResponse($messages);
    }
}
