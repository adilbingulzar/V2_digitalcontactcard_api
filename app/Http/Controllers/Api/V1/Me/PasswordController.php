<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdatePasswordRequest;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class PasswordController extends Controller
{
    /**
     * Changes the password and signs out every other device.
     */
    public function update(UpdatePasswordRequest $request): Response
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'legacy_password_md5' => null,
        ])->save();

        $currentToken = $user->currentAccessToken();
        $user->tokens()
            ->when($currentToken instanceof PersonalAccessToken, fn ($tokens) => $tokens->whereKeyNot($currentToken->getKey()))
            ->delete();

        return response()->noContent();
    }
}
