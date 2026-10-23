<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateEmailRequest;
use App\Http\Resources\UserResource;

class EmailController extends Controller
{
    /**
     * Changes the email address. The new address must be confirmed with a code
     * before the dashboard opens again; until then the old one is no longer used.
     */
    public function update(UpdateEmailRequest $request): UserResource
    {
        $user = $request->user();
        $email = $request->validated('email');

        if ($email !== $user->email) {
            $user->forceFill(['email' => $email, 'email_verified_at' => null])->save();
            $user->sendEmailVerificationNotification();
        }

        return new UserResource($user->load('business'));
    }
}
