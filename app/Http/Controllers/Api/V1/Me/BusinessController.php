<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateBusinessRequest;
use App\Http\Resources\UserResource;

class BusinessController extends Controller
{
    /**
     * Update the details of the business the signed-in user owns.
     */
    public function update(UpdateBusinessRequest $request): UserResource
    {
        $user = $request->user();
        $user->business->update($request->validated());

        return new UserResource($user->load('business'));
    }
}
