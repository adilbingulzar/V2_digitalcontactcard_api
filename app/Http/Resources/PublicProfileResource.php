<?php

namespace App\Http\Resources;

use App\Actions\Tips\SplitTip;
use App\Models\Tip;
use App\Models\TipPageSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a customer sees on a public tip and review page. Contains nothing private:
 * no email, phone, address or account details.
 *
 * @mixin User
 */
class PublicProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ratings = $this->receivedTips()->countsAsFeedback()->whereNotNull('rating');

        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'avatar' => $this->avatarUrl(),
            'bio' => $this->bio,
            'can_receive_tips' => app(SplitTip::class)->canReceiveTips($this->resource),
            'page' => self::page($this->tipPageSetting),
            'tips' => self::tipOptions($this->resource),
            'rating' => [
                'average' => round((float) (clone $ratings)->avg('rating'), 1),
                'count' => (clone $ratings)->count(),
            ],
            'pinned_reviews' => self::pinnedReviews($this->tipPageSetting, fn () => $this->receivedTips()),
        ];
    }

    /**
     * Look and texts of a tip page.
     *
     * @return array<string, mixed>
     */
    /**
     * Reviews the user pinned, shown under the review form when the user switched
     * that on ("What customers think about me ?" in the old app).
     *
     * @param  callable(): HasMany<Tip, User>|Builder<Tip>  $tips
     * @return list<array{rating: ?int, review: string}>
     */
    public static function pinnedReviews(?TipPageSetting $page, callable $tips): array
    {
        if (! $page?->pin_reviews) {
            return [];
        }

        return $tips()
            ->countsAsFeedback()
            ->where('is_review_pinned', true)
            ->whereNotNull('review')
            ->where('review', '!=', '')
            ->latest('id')
            ->limit(10)
            ->get(['rating', 'review'])
            ->map(fn (Tip $tip): array => ['rating' => $tip->rating, 'review' => (string) $tip->review])
            ->all();
    }

    public static function page(?TipPageSetting $page): array
    {
        return [
            'mode' => match ($page?->default_page) {
                TipPageSetting::PAGE_REVIEW_ONLY => 'review',
                TipPageSetting::PAGE_TIP_ONLY => 'tip',
                default => 'tip_and_review',
            },
            'header' => $page?->page_header,
            'title' => $page?->page_title,
            'success_header' => $page?->success_header,
            'success_message' => $page?->success_message,
            'show_email_field' => $page?->show_email_field ?? true,
            'show_review_field' => $page?->show_review_field ?? true,
            'theme' => [
                'background_image' => $page?->backgroundImageUrl(),
                'panel_color' => $page?->panel_color,
                'page_color' => $page?->page_color,
                'text_color' => $page?->text_color,
                'button_style' => $page?->buttonStyle() ?? 'orange',
                'emoji_color' => $page?->emoji_color,
                'emoji_type' => $page?->emoji_type ?? 0,
                'review_style' => $page?->review_style ?? 1,
            ],
            'preset_amounts' => array_map(
                fn (int|float|string $amount): string => number_format((float) $amount, 2, '.', ''),
                $page?->preset_amounts ?: [5, 10, 15],
            ),
        ];
    }

    /**
     * Limits and payment methods for tipping, based on the user whose settings apply.
     *
     * @return array<string, mixed>
     */
    public static function tipOptions(User $feePayer): array
    {
        return [
            'currency' => config('tiqs.tips.currency'),
            'min_amount' => config('tiqs.tips.min_amount'),
            'max_amount' => config('tiqs.tips.max_amount'),
            'payment_methods' => collect($feePayer->offeredPaymentMethods())
                ->map(fn (array $method, int $id): array => [
                    'id' => $id,
                    'name' => $method['name'],
                    'icon' => $method['icon'],
                    'fee_percent' => $method['fee_percent'],
                    'fee_fixed' => $method['fee_fixed'],
                ])
                ->values(),
        ];
    }
}
