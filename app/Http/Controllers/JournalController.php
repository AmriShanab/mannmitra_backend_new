<?php

namespace App\Http\Controllers;

use App\Http\Requests\JournalRequest;
use App\Http\Resources\JournalResource;
use App\Services\JournalService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    use ApiResponse;
    protected $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    public function store(JournalRequest $request)
    {
        
        $entry = $this->journalService->createJournal(
            $request->user()->id,
            $request->validated()
        );

        return $this->successResponse(new JournalResource($entry), 'Journal Entry Successfully', 201);
    }

    public function index(Request $request)
    {
        $entries = $this->journalService->getHistory($request->user()->id);

        return $this->successResponse(JournalResource::collection($entries));
    }

    public function show(Request $request, $id)
    {
        $entry = $this->journalService->getDetail($request->user()->id, $id);
        return $this->successResponse(new JournalResource($entry));
    }

    public function destroy(Request $request, $id)
    {
        $this->journalService->removeEntry($request->user()->id, $id);
        return $this->successResponse(null, 'Journal Entry Deleted');
    }
}
