<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicVcardResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class VcardController extends Controller
{
    /**
     * The public business card behind /vcards/{slug}.
     */
    public function show(User $user): PublicVcardResource
    {
        abort_unless($user->vcard?->is_active, 404);

        return new PublicVcardResource($user->load('vcardTheme', 'tipPageSetting', 'business'));
    }

    /**
     * The card's address for a user id: old printed QR codes for wallet passes
     * (/redirectcard/google/{id}, /getdirectioscard/{id}) used the numeric id.
     */
    public function slug(int $userId): JsonResponse
    {
        $user = User::query()->whereKey($userId)->whereHas('vcard', fn ($cards) => $cards->where('is_active', true))->firstOrFail();

        return response()->json(['data' => ['slug' => $user->slug]]);
    }
}
