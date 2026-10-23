<?php

namespace App\Models;

use Database\Factories\VcardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Digital business card shown at /vcards/{user slug}, in one of four layouts
 * (template) and one brand colour (accent_color). Cards saved before the card
 * builder have neither and take them from their old theme: theme 1 was the fixed
 * yellow and dark design, theme 2 used the user's own colours (VcardTheme).
 */
#[Fillable([
    'business_name', 'business_title', 'business_email', 'business_address', 'latitude', 'longitude',
    'logo', 'banner', 'theme', 'template', 'accent_color', 'section_order', 'design', 'social_links', 'google_review_url', 'ambassador_url', 'appointment_url',
    'show_business_name', 'show_location', 'show_opening_hours', 'show_review', 'show_business_icon',
    'show_email_icon', 'show_phone_icon', 'show_address_icon', 'is_active',
])]
class Vcard extends Model
{
    /** @use HasFactory<VcardFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'theme' => 'integer',
            'social_links' => 'array',
            'section_order' => 'array',
            'design' => 'array',
            'show_business_name' => 'boolean',
            'show_location' => 'boolean',
            'show_opening_hours' => 'boolean',
            'show_review' => 'boolean',
            'show_business_icon' => 'boolean',
            'show_email_icon' => 'boolean',
            'show_phone_icon' => 'boolean',
            'show_address_icon' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public const int THEME_GREEN = 1;

    public const int THEME_CUSTOM = 2;

    public const array TEMPLATES = ['classic', 'modern', 'minimal', 'bold'];

    /**
     * The parts of a card below its header, in their standard order.
     */
    public const array SECTIONS = ['quick_actions', 'save_contact', 'contact', 'location', 'opening_hours', 'buttons', 'reviews', 'social'];

    /**
     * Look of a card that is not saved yet.
     */
    public const string NEW_TEMPLATE = 'classic';

    public const string NEW_ACCENT_COLOR = '#1d4ed8';

    /**
     * The layout the card is shown in. The old yellow and dark design looks most like "bold".
     */
    public function resolvedTemplate(): string
    {
        if (in_array($this->template, self::TEMPLATES, true)) {
            return $this->template;
        }
        if (! $this->exists) {
            return self::NEW_TEMPLATE;
        }

        return $this->theme === self::THEME_CUSTOM ? 'classic' : 'bold';
    }

    /**
     * The owner's order of the card's parts: known parts once each, with any
     * part the owner did not place added in its standard position at the end.
     *
     * @return list<string>
     */
    public static function sectionOrder(mixed $order): array
    {
        $chosen = is_array($order) ? array_values(array_unique(array_filter($order, fn (mixed $section): bool => in_array($section, self::SECTIONS, true)))) : [];

        return [...$chosen, ...array_values(array_diff(self::SECTIONS, $chosen))];
    }

    /**
     * @return list<string>
     */
    public function resolvedSectionOrder(): array
    {
        return self::sectionOrder($this->section_order);
    }

    /**
     * The brand colour of the card: the chosen one, else the main colour of the old theme.
     */
    public function resolvedAccentColor(?VcardTheme $theme): string
    {
        if (is_string($this->accent_color) && preg_match('/^#[0-9a-f]{6}$/i', $this->accent_color) === 1) {
            return strtolower($this->accent_color);
        }
        if (! $this->exists) {
            return self::NEW_ACCENT_COLOR;
        }

        return VcardTheme::paletteFor($this, $theme)[$this->theme === self::THEME_CUSTOM ? 'primary_background' : 'secondary_button'];
    }

    public function logoUrl(): ?string
    {
        return self::imageUrl($this->logo, 'vcard-logos/', 'business');
    }

    /**
     * Without an own banner the card shows the old app's default picture.
     */
    public function bannerUrl(): ?string
    {
        return self::imageUrl($this->banner === 'default.jpg' ? null : $this->banner, 'vcard-banners/', 'vcard');
    }

    /**
     * New uploads live on the public disk; older ones are still served by the old app.
     */
    private static function imageUrl(?string $path, string $prefix, string $legacyFolder): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, $prefix)) {
            return Storage::disk('public')->url($path);
        }

        $legacy = config('tiqs.legacy_assets_url');

        return $legacy === '' ? null : $legacy.'/images/'.$legacyFolder.'/'.rawurlencode($path);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
