<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateProfileRequest;
use App\Http\Resources\UserResource;

class ProfileController extends Controller
{
    /**
     * Update the signed-in user's personal details and public URL name.
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->validated();
        if (array_key_exists('country_code', $data)) {
            $data['country_code'] = ltrim((string) $data['country_code'], '+') ?: null;
        }
        $user->update($data);

        return new UserResource($user->load('business'));
    }
}
