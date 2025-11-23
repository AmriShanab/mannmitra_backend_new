<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    public function successResponse($data, $message= 'Success', $code=200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    public function errorResponse($message = 'Error', $code=400)
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => null
        ], $code);
    }
}