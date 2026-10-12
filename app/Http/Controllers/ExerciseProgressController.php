<?php

namespace App\Http\Controllers;

use App\Domain\Exercises\ExerciseProgressService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ExerciseProgressController extends Controller
{
    use ApiResponse;

    public function __construct(protected ExerciseProgressService $progress)
    {
    }

    public function summary(Request $request)
    {
        return $this->successResponse($this->progress->summary($request->user()));
    }

    public function gratitude(Request $request)
    {
        $entries = $this->progress->gratitudeEntries($request->user())->map(fn($e) => [
            'id' => $e->id,
            'date' => $e->entry_date->toDateString(),
            'items' => array_values((array) $e->items),
        ]);

        return $this->successResponse($entries);
    }

    public function thoughtRecords(Request $request)
    {
        $records = $this->progress->thoughtRecords($request->user())->map(fn($r) => [
            'id' => $r->id,
            'situation' => $r->situation,
            'automatic_thought' => $r->automatic_thought,
            'distortion' => $r->distortion,
            'intensity_before' => $r->intensity_before,
            'evidence_against' => $r->evidence_against,
            'balanced_thought' => $r->balanced_thought,
            'intensity_after' => $r->intensity_after,
            'created_at' => $r->created_at,
        ]);

        return $this->successResponse($records);
    }
}
