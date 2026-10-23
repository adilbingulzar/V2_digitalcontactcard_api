<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Images\StoreImage;
use App\Http\Controllers\Controller;
use App\Http\Resources\TipPageSettingResource;
use App\Models\TipPageSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipPageBackgroundController extends Controller
{
    /**
     * Upload a background image for the tip page card.
     */
    public function store(Request $request, StoreImage $storeImage): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $settings = $request->user()->tipPageSetting()->firstOrCreate([], [
            'default_page' => TipPageSetting::PAGE_TIP_AND_REVIEW,
            'preset_amounts' => [5, 10, 15],
        ]);
        $previous = $settings->background_image;

        $settings->update(['background_image' => $storeImage->handle($request->file('image'), 'tip-backgrounds', 1600)]);
        $storeImage->delete($previous);

        return (new TipPageSettingResource($settings))->response()->setStatusCode(200);
    }

    /**
     * Remove the background image.
     */
    public function destroy(Request $request, StoreImage $storeImage): JsonResponse
    {
        $settings = $request->user()->tipPageSetting()->firstOrCreate([], [
            'default_page' => TipPageSetting::PAGE_TIP_AND_REVIEW,
            'preset_amounts' => [5, 10, 15],
        ]);
        $storeImage->delete($settings->background_image);
        $settings->update(['background_image' => null]);

        return (new TipPageSettingResource($settings))->response()->setStatusCode(200);
    }
}
