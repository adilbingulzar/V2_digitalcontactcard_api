<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Sign in with email and password and receive an API token.
     */
    public function store(LoginRequest $request, AuthenticateUser $authenticate): JsonResponse
    {
        $user = $request->authenticate($authenticate);

        return response()->json([
            'token' => $user->issueApiToken($request->validated('device_name')),
            'user' => new UserResource($user->load('business')),
        ]);
    }

    /**
     * Sign out: revoke the token used for this request.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
