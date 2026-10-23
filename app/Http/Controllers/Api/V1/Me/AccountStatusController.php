<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountStatusController extends Controller
{
    /**
     * Temporarily deactivate the account, or activate it again. While
     * deactivated the user gets no share of team and spot tips.
     */
    public function update(Request $request): UserResource
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Deactivated->value])],
        ]);

        $user = $request->user();
        // An account whose email is not confirmed yet cannot make itself active.
        abort_if($user->status === UserStatus::Pending, 403);

        $user->forceFill(['status' => $validated['status']])->save();

        return new UserResource($user->load('business'));
    }
}
