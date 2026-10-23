<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Images\StoreImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateTipPageRequest;
use App\Http\Resources\TipPageSettingResource;
use App\Models\TipPageSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipPageController extends Controller
{
    /**
     * The signed-in user's tip page settings (defaults when never saved).
     */
    public function show(Request $request): TipPageSettingResource
    {
        return new TipPageSettingResource($this->settingsOf($request->user()));
    }

    public function update(UpdateTipPageRequest $request, StoreImage $storeImage): JsonResponse
    {
        $user = $request->user();
        $settings = $this->settingsOf($user);

        $settings->fill([
            'default_page' => match ($request->validated('mode')) {
                'review' => TipPageSetting::PAGE_REVIEW_ONLY,
                'tip' => TipPageSetting::PAGE_TIP_ONLY,
                default => TipPageSetting::PAGE_TIP_AND_REVIEW,
            },
            'page_header' => $request->validated('header'),
            'page_title' => $request->validated('title'),
            'success_header' => $request->validated('success_header'),
            'success_message' => $request->validated('success_message'),
            'page_color' => $request->validated('page_color'),
            'panel_color' => $request->validated('panel_color'),
            'text_color' => $request->validated('text_color'),
            'emoji_color' => $request->validated('emoji_color'),
            'button_color' => $request->validated('button_style'),
            'emoji_type' => $request->validated('emoji_type'),
            'show_email_field' => $request->validated('show_email_field'),
            'show_review_field' => $request->validated('show_review_field'),
            'preset_amounts' => array_map(fn (string|int|float $amount): float => round((float) $amount, 2), $request->validated('preset_amounts')),
            ...$request->safe()->only(['pin_reviews', 'review_style']),
        ]);

        if ($request->has('pattern')) {
            $pattern = $request->validated('pattern');
            if ($pattern !== null) {
                // A pattern replaces an uploaded background, as in the old app.
                $storeImage->delete($settings->background_image);
                $settings->background_image = 'pattern/'.$pattern.'.png';
            } elseif ($settings->pattern() !== null) {
                $settings->background_image = null;
            }
        }
        $settings->save();

        if ($request->has('payment_methods')) {
            // None ticked means: offer all of them.
            $user->paymentMethod()->updateOrCreate([], [
                'pay_option_ids' => array_values(array_map('intval', $request->validated('payment_methods'))),
                'is_active' => true,
            ]);
        }

        // 200 also when this first save created the row: the client saves settings, it does not create a resource.
        return (new TipPageSettingResource($settings))->response()->setStatusCode(200);
    }

    private function settingsOf(User $user): TipPageSetting
    {
        return $user->tipPageSetting ?? $user->tipPageSetting()->make([
            'default_page' => TipPageSetting::PAGE_TIP_AND_REVIEW,
            'preset_amounts' => [5, 10, 15],
            'review_style' => 1,
            'is_active' => true,
        ]);
    }
}
