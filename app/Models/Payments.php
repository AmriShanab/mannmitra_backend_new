<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payments extends Model
{
    // Define exactly which columns can be written to
    protected $fillable = [
        'user_id',
        'ticket_id',
        'transaction_id',
        'amount',
        'status'
    ];
}