<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMoodRequest;
use App\Http\Resources\MoodResource;
use App\Services\MoodService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function dailyVibe(Request $request)
    {
        $user = Auth::user();
        $targetDate = \Carbon\Carbon::today()->toDateString();

        // This will now either be a number (e.g., 80) or null
        $moodPercentage = $this->moodService->getMoodByDate($user->id, $targetDate);

        // Check if the service returned null
        if (is_null($moodPercentage)) {
            return response()->json([
                'status' => true, // Better to use boolean true instead of string 'true'
                'message' => 'No mood record for this date',
                'data' => [
                    'date' => $targetDate,
                    'has_mood' => false,
                    'score' => null,
                    'percentage' => null,
                ]
            ]);
        }

        // If it's not null, return the success response
        return response()->json([
            'status' => true,
            'message' => 'Daily vibe retrieved successfully',
            'data' => [
                'date' => $targetDate,
                'has_mood' => true,
                'percentage' => round($moodPercentage, 0),
            ]
        ]);
    }
}
