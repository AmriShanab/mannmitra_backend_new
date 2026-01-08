<?php

namespace App\Http\Controllers;

use App\Services\TicketService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    use ApiResponse; // <--- STEP 1: Enable the Trait inside the class

    protected $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    public function create(Request $request)
    {
        $request->validate([
            'subject' => 'required|string'
        ]);

        $user = Auth::user();

        // if(!$user->is_paid){
        //     return response()->json(['status' => false, 'message' => 'Payment required to create a ticket'], 402);
        // }

        $activeTicket = $this->ticketService->getUserActiveTicket($user->id);
        if($activeTicket){
            return response()->json(['status' => false, 'message' => 'You already have an active ticket'], 400);
        }

        $ticket = $this->ticketService->initTicket(Auth::id(), $request->subject);
        
        // STEP 2: Use $this->successResponse() instead of ApiResponse::success()
        return $this->successResponse($ticket, 'Ticket created successfully');
    }

    public function paymentSuccess(Request $request)
    {
        $request->validate([
            'ticket_id' => 'required',
            'amount' => 'required',
            'transaction_id' => 'required'
        ]);

        $ticket = $this->ticketService->processPayment(
            $request->ticket_id,
            $request->transaction_id,
            $request->amount
        );

        return $this->successResponse($ticket, 'Payment processed and ticket updated');
    }

    public function listOpen()
    {
        $tickets = $this->ticketService->getPool();
        return $this->successResponse($tickets, 'Open tickets retrieved successfully');
    }

    public function accept($id)
    {
        try {
            $ticket = $this->ticketService->acceptTicket($id, Auth::id());
            return $this->successResponse($ticket, 'Ticket accepted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function getTicketsByStatus($status)
    {
        try {
            $userId = Auth::id(); // Get the currently logged-in user
            
            $tickets = $this->ticketService->getUserTicketsByStatus($userId, $status);
            
            return $this->successResponse($tickets, "Tickets with status '$status' retrieved successfully");

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}