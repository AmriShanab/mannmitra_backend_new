<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PsychiatristController extends Controller
{
    public function index()
    {
        return view('psychiatrist.dashboard', [
            'user' => Auth::user(),
        ]);
    }
}
