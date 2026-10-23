<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetLinkController extends Controller
{
    /**
     * Email a password reset link. The response is the same whether or not the
     * email belongs to an account, so it cannot be used to find out who is registered.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        Password::sendResetLink(['email' => Str::lower(trim($request->string('email')->toString()))]);

        return response()->json([
            'message' => 'If an account exists for this email, we have sent a link to reset the password.',
        ]);
    }
}
