<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLanguageRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function updateLanguage(UpdateLanguageRequest $request)
    {
        $user = $this->userService->changeAppLanguage(
            $request->user()->id, 
            $request->languageCode
        );

        return $this->successResponse(
            new UserResource($user), 
            'Language updated successfully'
        );
    }

    public function me(Request $request) 
    {
        return $this->successResponse(new UserResource($request->user()));
    }
}
