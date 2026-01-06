<?php

namespace App\Http\Controllers;

use App\Models\Tickets;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListnerWebController extends Controller
{
    protected $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    public function index()
    {
        $poolTickets = $this->ticketService->getPool();
        $myTickets = Tickets::where('listener_id', Auth::id())->where('status', 'in_progress')->get();
        return view('listener.dashboard', compact('poolTickets', 'myTickets'));
    }

    public function acceptTicket($id)
    {
        try {
            // This calls the Service we built earlier to assign the listener
            $ticket = $this->ticketService->acceptTicket($id, Auth::id());

            // Redirect to the chat room route with the ticket's unique ID
            return redirect()->route('chat', ['ticket_id' => $ticket->ticket_id]);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function chatRoom($ticket_id)
    {
        $ticket = Tickets::where('ticket_id', $ticket_id)->firstOrFail();
        if ($ticket->listener_id !== Auth::id()) {
            abort(403, "You are not assigned to this ticket.");
        }

        return view('listener.chat_room', compact('ticket'));
    }
}
