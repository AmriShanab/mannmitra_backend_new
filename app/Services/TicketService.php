<?php

namespace App\Services;

use App\Interfaces\TicketRepositoryInterface;
use App\Models\Payments;
use Exception;
use Illuminate\Support\Str;

class TicketService
{
    protected $ticketRepo;

    public function __construct(TicketRepositoryInterface $ticketRepo)
    {
        $this->ticketRepo = $ticketRepo;
    }

    // 1. UPDATED: Accepts the Razorpay Order ID from the controller
    public function initTicket($userId, $subject, $razorpayOrderId)
    {
        return $this->ticketRepo->createTicket([
            'ticket_id' => "TKT-" . strtoupper(Str::random(10)),
            'user_id' => $userId,
            'subject' => $subject,
            'status' => 'pending_payment', // UPDATED: Starts as pending, not open
            'razorpay_order_id' => $razorpayOrderId // UPDATED: Saving the order ID
        ]);
    }

    public function processPayment($ticketId, $txnId, $amount)
    {
        // 2. This repo method should ideally update the ticket status to 'open'
        $ticket = $this->ticketRepo->markAsPaid($ticketId);

        Payments::create([
            'user_id' => $ticket->user_id,
            'ticket_id' => $ticket->id,
            'transaction_id' => $txnId,
            'amount' => $amount,
        ]);

        return $ticket;
    }

    public function acceptTicket($ticketId, $listnerId)
    {
        $result = $this->ticketRepo->assignListener($ticketId, $listnerId);
        if (!$result) {
            throw new Exception("Ticket already assigned", 400);
        }

        return $result;
    }

    public function getPool()
    {
        return $this->ticketRepo->getUnassignedTickets();
    }

    public function getUserActiveTicket($userId)
    {
        return $this->ticketRepo->getActiveTicketByUser($userId);
    }

    public function getUserTicketsByStatus($userId, $status)
    {
        $validStatuses = ['open', 'closed', 'pending_payment', 'in_progress'];

        if (!in_array($status, $validStatuses)) {
            throw new \Exception("Invalid status provided. Allowed: " . implode(', ', $validStatuses), 400);
        }

        return $this->ticketRepo->getTicketsByUserIdAndStatus($userId, $status);
    }
}