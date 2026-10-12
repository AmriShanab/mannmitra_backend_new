<?php

namespace App\Http\Controllers;

use App\Models\UserGoal;
use App\Services\GoalService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Support\Language;

/** Viewing the weekly goal and checking in on it is free; only the guided exercise is paid. */
class GoalController extends Controller
{
    use ApiResponse;

    public function __construct(protected GoalService $goals)
    {
    }

    public function index(Request $request)
    {
        return $this->successResponse($this->goals->overview($request->user()->id, $this->lang($request)));
    }

    public function checkIn(Request $request, int $id)
    {
        $data = $request->validate(['progress' => 'required|in:' . implode(',', GoalService::PROGRESS)]);

        $goal = UserGoal::where('user_id', $request->user()->id)->where('id', $id)->where('status', 'active')->first();
        if (!$goal) {
            return $this->errorResponse('Goal not found.', 404);
        }

        $this->goals->checkIn($goal, $data['progress']);

        return $this->successResponse($this->goals->overview($request->user()->id, $this->lang($request)), 'Check-in saved');
    }

    private function lang(Request $request): string
    {
        return Language::exerciseLang($request->user()->language);
    }
}
