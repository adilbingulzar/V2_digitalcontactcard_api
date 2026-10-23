<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SsoLoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class SsoSessionController extends Controller
{
    /**
     * Sign in a user coming from the itsmycontact.info login form.
     *
     * Called server to server by the Next.js /login/sso route, which checks the
     * browser's Origin and turns the token into a session cookie. Unconfirmed
     * accounts are refused, as in the old app.
     */
    public function store(SsoLoginRequest $request, AuthenticateUser $authenticate): JsonResponse
    {
        abort_unless(config('tiqs.sso.enabled'), 404);

        if (! $request->hasValidSignature()) {
            return response()->json(['message' => 'The login request could not be verified. Please try again.'], 403);
        }

        $user = $request->authenticate($authenticate);

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Please confirm your email address before signing in.'], 403);
        }

        return response()->json([
            'token' => $user->issueApiToken('sso'),
            'user' => new UserResource($user->load('business')),
        ]);
    }
}
