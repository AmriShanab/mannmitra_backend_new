<?php

namespace App\Http\Controllers;

use App\Services\AiCompanionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiCompanionController extends Controller
{
    protected $companionService;

    // Inject the service through the constructor
    public function __construct(AiCompanionService $companionService)
    {
        $this->companionService = $companionService;
    }

    public function interact(Request $request)
    {
        $request->validate([
            'input_type' => 'required|string|in:init,buttons,emoji_slider,text_input,voice_record,crisis_contacted,init_cbt',
            'input_value' => 'nullable',
        ]);

        $resultData = $this->companionService->processInteraction(
            Auth::user(),
            $request->input('input_type'),
            $request->input('input_value'),
            $request->file('input_value')
        );

        return response()->json([
            'status' => true,
            'data' => $resultData
        ]);
    }
}
