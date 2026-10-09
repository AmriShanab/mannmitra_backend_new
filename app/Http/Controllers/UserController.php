<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLanguageRequest;
use App\Http\Resources\UserResource;
use App\Services\AccountDeletionService;
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

    /**
     * Permanently delete the signed-in user's account and personal data.
     * The app must send {"confirm": "DELETE"} so it can't happen by accident.
     */
    public function deleteAccount(Request $request, AccountDeletionService $deletion)
    {
        $request->validate(['confirm' => 'required|in:DELETE']);

        $user = $request->user();

        if (!$deletion->canDelete($user)) {
            return $this->errorResponse('Staff accounts must be removed by an administrator.', 403);
        }

        $deletion->delete($user);

        return $this->successResponse(null, 'Your account and personal data have been deleted.');
    }
}
