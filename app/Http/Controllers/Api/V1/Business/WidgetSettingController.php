<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Models\WidgetSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Size and look of the review widget (the old "Widget settings" page).
 */
class WidgetSettingController extends BusinessController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->data($request)]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'frame' => ['required', 'in:full,fixed'],
            'width' => ['required', 'integer', 'min:10', 'max:2000'],
            'height' => ['required', 'integer', 'min:200', 'max:3000'],
            'dark' => ['required', 'boolean'],
            'scrollable' => ['required', 'boolean'],
        ]);
        if ($validated['frame'] === 'full' && $validated['width'] > 100) {
            $validated['width'] = 100;
        }

        $business = $this->business($request);
        $settings = WidgetSetting::query()->whereBelongsTo($business)->where('widget', 'review')->first() ?? new WidgetSetting(['widget' => 'review']);
        $settings->business()->associate($business);
        $settings->fill([
            'frame_size' => $validated['frame'] === 'full' ? '1' : '2',
            'width' => $validated['width'],
            'height' => $validated['height'],
            'theme' => $validated['dark'] ? 1 : 0,
            'is_scrollable' => $validated['scrollable'],
            'is_active' => true,
        ])->save();

        return response()->json(['data' => $this->data($request)]);
    }

    /**
     * @return array{business_id: int, frame: string, width: int, height: int, dark: bool, scrollable: bool}
     */
    private function data(Request $request): array
    {
        $business = $this->business($request);
        $settings = WidgetSetting::query()->whereBelongsTo($business)->where('widget', 'review')->first();
        $frame = $settings?->frame_size === '2' ? 'fixed' : 'full';

        return [
            'business_id' => $business->id,
            'frame' => $frame,
            'width' => $settings?->width ?? ($frame === 'full' ? 100 : 400),
            'height' => $settings?->height ?? 500,
            'dark' => $settings?->theme === 1,
            'scrollable' => $settings?->is_scrollable ?? true,
        ];
    }
}
