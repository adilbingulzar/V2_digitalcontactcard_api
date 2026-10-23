<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\VerificationCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    /**
     * Confirm the signed-in user's email with the code that was emailed to them.
     * Each code allows a limited number of attempts and expires.
     */
    public function store(Request $request): UserResource
    {
        $request->validate(['code' => ['required', 'string', 'digits:'.config('tiqs.auth.verification_code_length')]]);
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return new UserResource($user->load('business'));
        }

        $verificationCode = VerificationCode::query()
            ->whereBelongsTo($user)
            ->where('purpose', 'confirm_email')
            ->latest('id')
            ->first();

        if ($verificationCode === null || ! $verificationCode->isUsable()) {
            throw ValidationException::withMessages([
                'code' => 'This code has expired. Request a new one.',
            ]);
        }

        if (! Hash::check($request->string('code')->toString(), $verificationCode->code_hash)) {
            $verificationCode->increment('attempts');

            throw ValidationException::withMessages([
                'code' => 'This code is not correct.',
            ]);
        }

        $verificationCode->delete();
        $user->markEmailAsVerified();
        event(new Verified($user));

        return new UserResource($user->load('business'));
    }
}
