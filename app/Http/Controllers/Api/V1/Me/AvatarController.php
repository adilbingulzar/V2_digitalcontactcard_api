<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Images\StoreImage;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class AvatarController extends Controller
{
    /**
     * Upload a new profile photo; the previous one is removed.
     */
    public function store(Request $request, StoreImage $storeImage): UserResource
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $previous = $user->avatar;

        $user->forceFill(['avatar' => $storeImage->handle($request->file('image'), 'avatars', 600)])->save();
        $storeImage->delete($previous);

        return new UserResource($user->load('business'));
    }

    /**
     * Remove the profile photo.
     */
    public function destroy(Request $request, StoreImage $storeImage): UserResource
    {
        $user = $request->user();
        $storeImage->delete($user->avatar);
        $user->forceFill(['avatar' => null])->save();

        return new UserResource($user->load('business'));
    }
}
