<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    /**
     * The signed-in user's account.
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('business'));
    }
}
