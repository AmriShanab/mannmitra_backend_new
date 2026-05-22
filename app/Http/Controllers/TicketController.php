<?php

namespace App\Http\Controllers;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\ListenerTickets;
use App\Services\TicketService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class TicketController extends Controller
{
    use ApiResponse;

    protected $ticketService;
    protected $paymentGateway;

    public function __construct(TicketService $ticketService, PaymentGatewayInterface $paymentGateway)
    {
        $this->ticketService = $ticketService;
        $this->paymentGateway = $paymentGateway;
    }

    public function create(Request $request)
    {
        $request->validate(['subject' => 'required|string']);
        $user = Auth::user();

        if ($this->ticketService->getUserActiveTicket($user->id)) {
            return $this->errorResponse('You already have an active ticket', 400);
        }

        // Use the interface method!
        $order = $this->paymentGateway->createOrder(9900, 'INR');

        $ticket = $this->ticketService->initTicket($user->id, $request->subject, $order['id']);

        return $this->successResponse([
            'ticket' => $ticket,
            'payment_data' => $order
        ], 'Ticket created successfully');
    }

    public function paymentSuccess(Request $request)
    {
        $request->validate([
            'ticket_id' => 'required',
            'payment_id' => 'required',
            'signature' => 'required'
        ]);

        // Use the interface method!
        $isValid = $this->paymentGateway->verifyPayment([
            'payment_id' => $request->payment_id,
            'signature' => $request->signature
        ]);

        if (!$isValid) return $this->errorResponse('Invalid payment', 400);

        $ticket = $this->ticketService->processPayment($request->ticket_id, $request->payment_id, 99);
        return $this->successResponse($ticket, 'Payment verified');
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
            $userId = Auth::id();
            $tickets = $this->ticketService->getUserTicketsByStatus($userId, $status);
            return $this->successResponse($tickets, "Tickets with status '$status' retrieved successfully");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
