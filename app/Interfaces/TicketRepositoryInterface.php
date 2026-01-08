<?php

namespace App\Interfaces;

interface TicketRepositoryInterface
{
    public function createTicket(array $data);
    public function findById($id);
    public function getUnassignedTickets();
    public function assignListener($ticketId, $listenerId);
    public function markAsPaid($ticketId);
    public function getActiveTicketByUser($userId);
    public function getTicketsByUserIdAndStatus($userId, $status);
}