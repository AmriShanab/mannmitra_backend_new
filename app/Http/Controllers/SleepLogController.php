<?php

namespace App\Http\Controllers;

use App\Services\SleepLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** The sleep log is free to use; only the guided Sleep Wind-down exercise is paid. */
class SleepLogController extends Controller
{
    use ApiResponse;

    public function __construct(protected SleepLogService $sleep)
    {
    }

    public function index(Request $request)
    {
        return $this->successResponse($this->sleep->overview($request->user()->id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'hours' => 'required|numeric|min:0.5|max:16',
            'quality' => 'required|integer|min:1|max:3',
            'date' => 'nullable|date_format:Y-m-d',
        ]);

        $date = $data['date'] ?? now()->toDateString();
        if (!$this->sleep->validDate($date)) {
            return $this->errorResponse('Choose a date within the last 60 days.', 422);
        }

        $this->sleep->upsert($request->user()->id, $date, (float) $data['hours'], (int) $data['quality']);

        return $this->successResponse($this->sleep->overview($request->user()->id), 'Sleep logged', 201);
    }
}
