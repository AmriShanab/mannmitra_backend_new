<?php

namespace App\Services;

use App\Models\Activities;

class ActivityService
{
    public function getAllActivities()
    {
        $activities = Activities::all();
        return $activities;
    }
}