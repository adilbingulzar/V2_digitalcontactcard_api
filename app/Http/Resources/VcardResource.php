<?php

namespace App\Http\Resources;

use App\Models\Vcard;
use App\Models\VcardTheme;
use App\Services\CardDesign\CardDesign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user's own business card settings.
 *
 * @mixin Vcard
 */
class VcardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $theme = $this->user->vcardTheme;

        return [
            'exists' => $this->exists,
            'is_active' => $this->is_active ?? true,
            'theme' => $this->theme ?? Vcard::THEME_GREEN,
            'template' => $this->resolvedTemplate(),
            'accent_color' => $this->resolvedAccentColor($theme),
            'section_order' => $this->resolvedSectionOrder(),
            'design' => CardDesign::forOutput($this->design),
            'business_name' => $this->business_name,
            'business_title' => $this->business_title,
            'business_email' => $this->business_email,
            'business_address' => $this->business_address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'logo' => $this->logoUrl(),
            'banner' => $this->bannerUrl(),
            'social_links' => $this->social_links ?? [],
            'appointment_url' => $this->appointment_url,
            'google_review_url' => $this->google_review_url,
            'ambassador_url' => $this->ambassador_url,
            'show_business_name' => $this->show_business_name ?? true,
            'show_location' => $this->show_location ?? true,
            'show_opening_hours' => $this->show_opening_hours ?? true,
            'show_review' => $this->show_review ?? true,
            'show_business_icon' => $this->show_business_icon ?? true,
            'show_email_icon' => $this->show_email_icon ?? true,
            'show_phone_icon' => $this->show_phone_icon ?? true,
            'show_address_icon' => $this->show_address_icon ?? true,
            'colors' => array_merge(VcardTheme::DEFAULTS, array_filter($theme?->only(array_keys(VcardTheme::DEFAULTS)) ?? [])),
        ];
    }
}
