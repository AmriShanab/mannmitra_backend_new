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

    public function index()
    {
        try {
            $allActivities = $this->activityService->getAllActivities();
            return response()->json([
                'success' => true,
                'data' =>$allActivities
            ]);
        } catch (\Exception $e) {
            throw new \Exception('Error fetching activities: ' . $e->getMessage());
        }
    }
}
