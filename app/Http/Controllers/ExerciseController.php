<?php

namespace App\Http\Controllers;

use App\Services\ExerciseAccessService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExerciseController extends Controller
{
    use ApiResponse;

    public function __construct(protected ExerciseAccessService $access)
    {
    }

    public function createOrder(Request $request)
    {
        $request->validate(['activity_id' => 'required|string|max:64']);

        try {
            $order = $this->access->createOrder($request->user(), $request->activity_id);
            return $this->successResponse($order, 'Order created successfully', 201);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Throwable $e) {
            Log::error('Exercise order creation failed: ' . $e->getMessage());
            return $this->errorResponse('Unable to start payment. Please try again.', 502);
        }
    }

    public function purchase(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
            'transaction_id' => 'required|string',
            'signature' => 'required|string',
        ]);

        try {
            $user = $request->user();
            $credit = $this->access->confirmPurchase($user, $request->order_id, $request->transaction_id, $request->signature);

            return $this->successResponse([
                'activity_id' => $credit->activity_id,
                'sessions_left' => $this->access->sessionsLeft($user, $credit->activity_id),
            ], 'Exercise unlocked successfully');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Throwable $e) {
            Log::error('Exercise purchase failed: ' . $e->getMessage());
            return $this->errorResponse('Unlock failed. If you were charged, please contact support.', 500);
        }
    }
}
