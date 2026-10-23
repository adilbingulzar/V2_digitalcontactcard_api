<?php

namespace App\Http\Resources;

use App\Actions\Business\ReplaceShifts;
use App\Models\Tip;
use App\Models\TipPageSetting;
use App\Models\User;
use App\Models\Vcard;
use App\Models\VcardTheme;
use App\Services\CardDesign\CardDesign;
use App\Services\Wallet\AppleWalletPass;
use App\Services\Wallet\GoogleWalletPass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public business card behind /vcards/{slug}. Only what the owner chose to show is included.
 *
 * @mixin User
 */
class PublicVcardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Vcard $card */
        $card = $this->vcard;
        $reviewMode = $this->tipPageSetting?->default_page ?? TipPageSetting::PAGE_TIP_AND_REVIEW;

        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'avatar' => $this->avatarUrl(),
            'bio' => $this->bio,
            'phone' => filled($this->phone) ? trim('+'.ltrim((string) $this->country_code, '+').' '.$this->phone) : null,
            'theme' => $card->theme === Vcard::THEME_CUSTOM ? 'custom' : 'classic',
            'colors' => VcardTheme::paletteFor($card, $this->vcardTheme),
            'template' => $card->resolvedTemplate(),
            'accent_color' => $card->resolvedAccentColor($this->vcardTheme),
            'section_order' => $card->resolvedSectionOrder(),
            'design' => CardDesign::forOutput($card->design),
            'business' => [
                'name' => $card->show_business_name ? $card->business_name : null,
                'logo' => $card->show_business_name ? null : $card->logoUrl(),
                'title' => $card->business_title,
                'email' => $card->business_email,
                'address' => $card->show_location ? $card->business_address : null,
                'latitude' => $card->show_location ? $card->latitude : null,
                'longitude' => $card->show_location ? $card->longitude : null,
                'banner' => $card->bannerUrl(),
            ],
            'icons' => [
                'business' => $card->show_business_icon,
                'email' => $card->show_email_icon,
                'phone' => $card->show_phone_icon,
                'address' => $card->show_address_icon,
            ],
            'links' => [
                'appointment' => self::webUrl($card->appointment_url),
                'google_review' => self::webUrl($card->google_review_url),
                'tickets' => self::webUrl($card->ambassador_url),
            ],
            'social_links' => array_values(array_filter(array_map(self::webUrl(...), $card->social_links ?? []))),
            'opening_hours' => $card->show_opening_hours ? app(ReplaceShifts::class)->current('vcard', $this->id, null, null) : null,
            'can_review' => $card->show_review && $reviewMode !== TipPageSetting::PAGE_TIP_ONLY,
            'reviews' => $this->latestReviews(),
            'wallets' => [
                'google' => app(GoogleWalletPass::class)->isConfigured(),
                'apple' => app(AppleWalletPass::class)->isConfigured(),
            ],
        ];
    }

    /**
     * Only http(s) links are shown, so a stored "javascript:" link can never run on the card.
     */
    private static function webUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        return preg_match('#^https?://[^\s]+$#i', $url) === 1 ? $url : null;
    }

    /**
     * Six written reviews, pinned ones first, then the newest: of the whole business for a business owner, otherwise of the user.
     *
     * @return list<array{rating: ?int, review: string, name: ?string, date: ?string}>
     */
    private function latestReviews(): array
    {
        $business = $this->business;

        return Tip::query()
            ->countsAsFeedback()
            ->when($business !== null, fn ($tips) => $tips->where('business_id', $business->id), fn ($tips) => $tips->where('recipient_id', $this->id))
            ->whereNotNull('review')
            ->where('review', '!=', '')
            ->orderByDesc('is_review_pinned')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (Tip $tip): array => [
                'rating' => $tip->rating,
                'review' => $tip->review,
                'name' => $tip->reviewer_name,
                'date' => $tip->created_at?->toDateString(),
            ])
            ->all();
    }
}
