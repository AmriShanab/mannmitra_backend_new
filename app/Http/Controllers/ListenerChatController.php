<?php

namespace App\Http\Controllers;

use App\Models\ListerMessages;
use App\Models\Tickets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function endSession(Request $request)
    {
        $request->validate([
            'ticket_id' => 'required',
        ]);

        $ticket = Tickets::where('ticket_id', $request->ticket_id)->first();

        if ($ticket->listener_id !== Auth::id()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized to end this session'], 403);
        }

        $ticket->update([
            'status' => 'closed'
        ]);

        return response()->json(['status' => true, 'message' => 'Session ended successfully']);
    }
}
