<?php

namespace App\Models;

use Database\Factories\VcardThemeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Own colours of a business card that uses the custom theme.
 */
#[Fillable([
    'primary_background', 'secondary_background', 'primary_text', 'secondary_text', 'primary_button',
    'secondary_button', 'primary_button_text', 'secondary_button_text', 'border', 'return_button', 'is_active',
])]
class VcardTheme extends Model
{
    /** @use HasFactory<VcardThemeFactory> */
    use HasFactory;

    /**
     * Colours of a custom card until the user picks their own (the old app's defaults).
     */
    public const array DEFAULTS = [
        'primary_background' => '#000000',
        'secondary_background' => '#ffffff',
        'primary_text' => '#ffffff',
        'secondary_text' => '#000000',
        'primary_button' => '#ffffff',
        'secondary_button' => '#332d2d',
        'primary_button_text' => '#000000',
        'secondary_button_text' => '#ffffff',
        'border' => '#ffffff',
        'return_button' => '#f97316',
    ];

    /**
     * Fixed colours of the old app's first card design (called "green", though it is yellow and dark grey).
     */
    public const array CLASSIC = [
        'primary_background' => '#f9d254',
        'secondary_background' => '#242424',
        'primary_text' => '#000000',
        'secondary_text' => '#ffffff',
        'primary_button' => '#242424',
        'secondary_button' => '#f9d254',
        'primary_button_text' => '#ffffff',
        'secondary_button_text' => '#000000',
        'border' => '#f9d254',
        'return_button' => '#242424',
    ];

    /**
     * The colours a card is shown in: fixed for the classic design, otherwise
     * the user's own, with the defaults filling any colour not chosen.
     *
     * @return array<string, string>
     */
    public static function paletteFor(Vcard $card, ?self $theme): array
    {
        if ($card->theme !== Vcard::THEME_CUSTOM) {
            return self::CLASSIC;
        }

        $palette = self::DEFAULTS;
        foreach (array_keys(self::DEFAULTS) as $key) {
            $color = $theme?->{$key};
            if (is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color) === 1) {
                $palette[$key] = strtolower($color);
            }
        }

        return $palette;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
