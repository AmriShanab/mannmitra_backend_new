<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMoodRequest;
use App\Http\Resources\MoodResource;
use App\Services\MoodService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class MoodController extends Controller
{
    use ApiResponse;

    protected $moodService;

    public function __construct(MoodService $moodService)
    {
        $this->moodService = $moodService;
    }

    public function store(StoreMoodRequest $request)
    {
        $entry = $this->moodService->logMood($request->user()->id, $request->validated());

        return $this->successResponse(new MoodResource($entry), 'Mood Logged Successfully', 201);
    }

    public function index(Request $request)
    {
        $entries = $this->moodService->getTimeline($request->user()->id);

        return $this->successResponse(MoodResource::collection($entries));
    }

    public function checkRequired(Request $request)
    {
        $isRequired = $this->moodService->checkMondayRequirement($request->user()->id);
        return $this->successResponse(['requires_log' => $isRequired]);
    }

    public function dailySummary(Request $request)
    {
        $days = (int) $request->query('days', 14);
        $data = $this->moodService->generateDailySummary($request->user()->id, $days);

        return $this->successResponse($data);
    }

    public function weeklySummary(Request $request)
    {
        $data = $this->moodService->generateWeeklySummary($request->user()->id);

        return $this->successResponse($data);
    }
}
