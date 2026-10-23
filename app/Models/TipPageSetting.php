<?php

namespace App\Models;

use Database\Factories\TipPageSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Look and texts of a user's public tip and review page.
 */
#[Fillable([
    'profile_type', 'default_page', 'page_header', 'page_title', 'success_header', 'success_message',
    'theme', 'background_image', 'panel_color', 'page_color', 'text_color', 'emoji_color',
    'emoji_type', 'button_color', 'font_size', 'show_email_field', 'show_review_field',
    'review_style', 'pin_reviews', 'preset_amounts', 'is_active',
])]
class TipPageSetting extends Model
{
    /** @use HasFactory<TipPageSettingFactory> */
    use HasFactory;

    public const int PAGE_REVIEW_ONLY = 0;

    public const int PAGE_TIP_ONLY = 1;

    public const int PAGE_TIP_AND_REVIEW = 2;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profile_type' => 'integer',
            'default_page' => 'integer',
            'theme' => 'integer',
            'emoji_type' => 'integer',
            'review_style' => 'integer',
            'show_email_field' => 'boolean',
            'show_review_field' => 'boolean',
            'pin_reviews' => 'boolean',
            'preset_amounts' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Button style name: orange, red, blue, light or dark. New values are stored as
     * the name; the old app stored CSS classes such as "custom-btn btn-danger shadow-0".
     */
    public function buttonStyle(): string
    {
        $stored = (string) $this->button_color;

        return match (true) {
            in_array($stored, ['orange', 'red', 'blue', 'light', 'dark'], true) => $stored,
            str_contains($stored, 'btn-danger') => 'red',
            str_contains($stored, 'btn-info') => 'blue',
            str_contains($stored, 'btn-light') => 'light',
            str_contains($stored, 'btn-dark') => 'dark',
            default => 'orange',
        };
    }

    /** Background patterns users can pick, as in the old app; the files ship with the website. */
    public const array PATTERNS = ['patern1', 'pattern2', 'pattern3', 'pattern4', 'pattern5', 'pattern6'];

    /**
     * The chosen pattern ("pattern/pattern2.png" is stored as in the old app), or null.
     */
    public function pattern(): ?string
    {
        if (blank($this->background_image) || ! preg_match('#^pattern/([a-z0-9]+)\.png$#', $this->background_image, $match)) {
            return null;
        }

        return in_array($match[1], self::PATTERNS, true) ? $match[1] : null;
    }

    /**
     * Full URL of the card background: a pattern (served by the website),
     * uploaded to this app ("tip-backgrounds/…"), or one of the old app's
     * images (a bare name under assets/images/pattern).
     */
    public function backgroundImageUrl(): ?string
    {
        if (blank($this->background_image)) {
            return null;
        }

        if ($this->pattern() !== null) {
            return config('tiqs.frontend_url').'/images/pattern/'.$this->pattern().'.png';
        }

        if (str_starts_with($this->background_image, 'tip-backgrounds/')) {
            return Storage::disk('public')->url($this->background_image);
        }

        $legacy = config('tiqs.legacy_assets_url');
        if ($legacy === '') {
            return null;
        }

        $path = str_contains($this->background_image, 'pattern') ? $this->background_image : 'pattern/'.$this->background_image;

        return $legacy.'/images/'.implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
