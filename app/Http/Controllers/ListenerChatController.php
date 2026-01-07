<?php

namespace App\Http\Controllers;

use App\Models\ListerMessages;
use Illuminate\Http\Request;

class ListenerChatController extends Controller
{
    public function saveMessage(Request $request)
    {
        $message = ListerMessages::create([
            'ticket_id' => $request->ticket_id,
            'sender_id' => $request->sender_id,
            'message' => $request->message,
        ]);

        return response()->json(['message' => 'Message saved successfully', 'data' => $message], 201);
    }

    public function getHistory($ticket_id)
    {
        $messages = ListerMessages::where('ticket_id', $ticket_id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['status' => true, 'data' => $messages]);
    }
}
