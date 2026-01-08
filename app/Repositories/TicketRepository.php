<?php

namespace App\Repositories;
use App\Interfaces\TicketRepositoryInterface;
use App\Models\Tickets;

class TicketRepository implements TicketRepositoryInterface
{
    public function createTicket(array $data)
    {
        return Tickets::create($data);
    }

    public function findById($id)
    {
        return Tickets::findOrFail($id);
    } 

    public function getUnassignedTickets()
    {
        return Tickets::where('status', 'open')
                        ->whereNull('listener_id')
                        ->with('user:id,name')
                        ->latest()
                        ->get();
    }

    public function assignListener($ticketId, $listenerId)
    {
        $ticket = Tickets::findOrFail($ticketId);

        if($ticket->listener_id !== null){
            return false; // Already assigned
        }

        $ticket->update([
            'listener_id' => $listenerId,
            'status' => 'in_progress'
        ]);

        return $ticket;
    }

    public function markAsPaid($ticketId)
    {
        $ticket = Tickets::findOrFail($ticketId);

        $ticket->update([
            'status' => 'open',
            'payment_verified' => true
        ]);

        return $ticket;
    }

    public function getActiveTicketByUser($userId)
    {
        $activeTicket = Tickets::where('user_id', $userId)
                            ->whereIn('status', ['pending_payment', 'open', 'in_progress'])
                            ->first();

        return $activeTicket;
    }

    public function getTicketsByUserIdAndStatus($userId, $status)
    {
        return Tickets::where('user_id', $userId)
                        ->where('status', $status)
                        ->with('listener:id,name')
                        ->orderBy('created_at', 'desc')
                        ->get();
    }
}