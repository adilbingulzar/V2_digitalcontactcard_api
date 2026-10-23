<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateVcardRequest;
use App\Http\Resources\VcardResource;
use App\Models\User;
use App\Models\Vcard;
use App\Services\CardDesign\CardDesign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VcardController extends Controller
{
    /**
     * The user's business card, or a new one filled from their profile.
     */
    public function show(Request $request): VcardResource
    {
        return new VcardResource($this->card($request->user()));
    }

    public function update(UpdateVcardRequest $request): JsonResponse
    {
        $user = $request->user();
        $card = $this->card($user);

        DB::transaction(function () use ($request, $user, $card): void {
            $card->fill($request->safe()->except(['colors', 'design']));
            if ($request->exists('design')) {
                $card->design = (new CardDesign($user))->clean($request->input('design'));
            }
            // Published: the builder's work copy is no longer needed.
            $user->vcardDraft()->delete();
            $card->social_links = array_values($request->validated('social_links') ?? []);
            $card->user()->associate($user)->save();

            if ($request->filled('colors')) {
                $user->vcardTheme()->updateOrCreate([], [...$request->validated('colors'), 'is_active' => true]);
            }
        });

        return (new VcardResource($card->refresh()))->response()->setStatusCode(200);
    }

    private function card(User $user): Vcard
    {
        return $user->vcard ?? new Vcard([
            'business_name' => $user->business?->name,
            'business_email' => $user->business?->email ?? $user->email,
            'business_address' => $user->business?->address ?? $user->address,
        ])->setRelation('user', $user);
    }
}
