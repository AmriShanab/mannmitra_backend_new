<?php

namespace App\Http\Controllers;

use App\Services\ActivityService;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    private $activityService;
    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    public function index(Request $request)
    {
        try {
            $allActivities = $this->activityService->getAllActivities($request->user(), $request->query('lang'));
            return response()->json([
                'success' => true,
                'data' =>$allActivities
            ]);
        } catch (\Exception $e) {
            throw new \Exception('Error fetching activities: ' . $e->getMessage());
        }
    }
}
