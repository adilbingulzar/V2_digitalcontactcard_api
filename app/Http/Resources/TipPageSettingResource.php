<?php

namespace App\Http\Resources;

use App\Models\TipPageSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user's tip page settings, in the shape the settings form edits.
 *
 * @mixin TipPageSetting
 */
class TipPageSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'mode' => match ($this->default_page) {
                TipPageSetting::PAGE_REVIEW_ONLY => 'review',
                TipPageSetting::PAGE_TIP_ONLY => 'tip',
                default => 'tip_and_review',
            },
            'header' => $this->page_header,
            'title' => $this->page_title,
            'success_header' => $this->success_header,
            'success_message' => $this->success_message,
            'page_color' => $this->hexOrNull($this->page_color),
            'panel_color' => $this->hexOrNull($this->panel_color),
            'text_color' => $this->hexOrNull($this->text_color),
            'emoji_color' => $this->hexOrNull($this->emoji_color),
            'button_style' => $this->buttonStyle(),
            'emoji_type' => $this->emoji_type,
            'show_email_field' => $this->show_email_field,
            'show_review_field' => $this->show_review_field,
            'preset_amounts' => array_map(
                fn (int|float|string $amount): string => number_format((float) $amount, 2, '.', ''),
                $this->preset_amounts ?: [5, 10, 15],
            ),
            'background_image' => $this->backgroundImageUrl(),
            'pattern' => $this->pattern(),
            'pin_reviews' => (bool) $this->pin_reviews,
            'review_style' => $this->review_style ?? 1,
            'payment_methods' => $this->paymentMethods(),
        ];
    }

    /**
     * Public payment methods, and whether the user offers each one.
     *
     * @return list<array{id: int, name: string, icon: string, chosen: bool}>
     */
    private function paymentMethods(): array
    {
        $offered = $this->user->offeredPaymentMethods();
        $methods = [];
        foreach (config('payment_methods') as $id => $method) {
            if ($method['public']) {
                $methods[] = ['id' => $id, 'name' => $method['name'], 'icon' => $method['icon'], 'chosen' => array_key_exists($id, $offered)];
            }
        }

        return $methods;
    }

    /**
     * The old app accepted any colour text; the form edits #RRGGBB values only.
     */
    private function hexOrNull(?string $color): ?string
    {
        return $color !== null && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : null;
    }
}
