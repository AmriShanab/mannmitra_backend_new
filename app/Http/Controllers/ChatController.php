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
            'message' => 'required|string',
        ]);

        // TODO: Security Check - Ensure auth user owns this session
        // $user = $request->user();
        
        try {
            $response = $this->chatService->handleUserMessage(
                $request->session_id,
                $request->message
            );

            return $this->successResponse($response, 'Message sent successfully');

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