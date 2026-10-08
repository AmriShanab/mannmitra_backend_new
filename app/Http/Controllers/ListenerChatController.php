<?php

namespace App\Http\Controllers;

use App\Models\ListerMessages;
use App\Models\Tickets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListenerChatController extends Controller
{
    /** The ticket if the logged-in user is its owner or its assigned listener. */
    private function participantTicket(string $ticketCode): ?Tickets
    {
        $ticket = Tickets::where('ticket_id', $ticketCode)->first();
        if (!$ticket) {
            return null;
        }

        $id = (int) Auth::id();
        $isParticipant = (int) $ticket->user_id === $id
            || ($ticket->listener_id && (int) $ticket->listener_id === $id);

        return $isParticipant ? $ticket : null;
    }

    public function saveMessage(Request $request)
    {
        $data = $request->validate([
            'ticket_id' => 'required|string',
            'message' => 'required|string|max:2000',
        ]);

        $ticket = $this->participantTicket($data['ticket_id']);
        if (!$ticket) {
            return response()->json(['status' => false, 'message' => 'Ticket not found'], 404);
        }

        if (!in_array($ticket->status, ['open', 'in_progress'], true)) {
            return response()->json(['status' => false, 'message' => 'This session is not active'], 409);
        }

        // Sender always comes from the login, never from the request body.
        $message = ListerMessages::create([
            'ticket_id' => $ticket->ticket_id,
            'sender_id' => Auth::id(),
            'message' => $data['message'],
        ]);

        return response()->json(['status' => true, 'message' => 'Message saved successfully', 'data' => $message], 201);
    }

    public function getHistory($ticket_id)
    {
        $ticket = $this->participantTicket($ticket_id);
        if (!$ticket) {
            return response()->json(['status' => false, 'message' => 'Ticket not found'], 404);
        }

        $messages = ListerMessages::where('ticket_id', $ticket->ticket_id)
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

        if (!$ticket) {
            return response()->json(['status' => false, 'message' => 'Ticket not found'], 404);
        }

        if ((int) $ticket->listener_id !== (int) Auth::id()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized to end this session'], 403);
        }

        $ticket->update([
            'status' => 'closed'
        ]);

        return response()->json(['status' => true, 'message' => 'Session ended successfully']);
    }
}
