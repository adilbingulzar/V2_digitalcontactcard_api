<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VerificationCodeController extends Controller
{
    /**
     * Email the signed-in user a new confirmation code. The previous code stops working.
     */
    public function store(Request $request): Response
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->noContent();
    }
}
