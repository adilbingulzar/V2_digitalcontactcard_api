<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class RegisteredUserController extends Controller
{
    /**
     * Create an account, email its confirmation code and sign it in.
     */
    public function store(RegisterRequest $request, RegisterUser $register): JsonResponse
    {
        $user = $register->handle($request->safe()->except(['password_confirmation', 'accept_terms']));

        return response()->json([
            'token' => $user->issueApiToken(),
            'user' => new UserResource($user->load('business')),
        ], 201);
    }
}
