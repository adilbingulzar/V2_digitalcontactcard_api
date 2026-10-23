<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Images\StoreImage;
use App\Http\Controllers\Controller;
use App\Http\Resources\VcardResource;
use App\Models\Vcard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The logo and the banner picture of the user's business card.
 */
class VcardImageController extends Controller
{
    private const array FOLDERS = ['logo' => ['vcard-logos', 600], 'banner' => ['vcard-banners', 1600]];

    public function store(Request $request, string $type, StoreImage $storeImage): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=40,min_height=40'],
        ]);

        $card = $this->card($request);
        [$folder, $maxDimension] = self::FOLDERS[$type];
        $previous = $card->{$type};

        $card->forceFill([$type => $storeImage->handle($request->file('image'), $folder, $maxDimension)])->save();
        $storeImage->delete($previous);

        return (new VcardResource($card))->response()->setStatusCode(200);
    }

    public function destroy(Request $request, string $type, StoreImage $storeImage): JsonResponse
    {
        $card = $this->card($request);
        abort_if($type === 'logo' && ! $card->show_business_name, 422, 'Show the business name before removing the logo.');

        $storeImage->delete($card->{$type});
        $card->forceFill([$type => null])->save();

        return (new VcardResource($card))->response()->setStatusCode(200);
    }

    /**
     * Images can be added before the card's details are saved the first time.
     */
    private function card(Request $request): Vcard
    {
        $user = $request->user();

        return $user->vcard()->firstOrCreate([], [
            'business_name' => $user->business?->name,
            'business_email' => $user->business?->email ?? $user->email,
        ]);
    }
}
