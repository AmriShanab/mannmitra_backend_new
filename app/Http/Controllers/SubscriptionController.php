<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    protected $subscriptionService;
    use ApiResponse;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_type' => 'required|in:monthly,yearly',
            'transaction_id' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        try {
            $subscription = $this->subscriptionService->processNewSubscription(
                Auth::user(),
                $request->plan_type,
                $request->transaction_id,
                $request->amount
            );

            return $this->successResponse($subscription, 'Subscription created successfully');
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Subscription failed: ' . $e->getMessage()], 500);
        }

    }

    public function status()
    {
        $user = Auth::user();
        $subscription = $this->subscriptionService->getUserSubscription($user->id);

        if(!$subscription){
            return response()->json(['status' => false, 'message' => 'No active subscription found'], 404);
        }

        return $this->successResponse($subscription, 'Active subscription retrieved successfully');
    }
    

}
