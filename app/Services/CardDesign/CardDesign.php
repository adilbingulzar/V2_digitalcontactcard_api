<?php

namespace App\Services\CardDesign;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The owner's own design of a business card: a theme (colours, fonts, corners)
 * and an ordered list of blocks, each with its own settings and style.
 *
 * Designs come from the card builder and from the AI assistant, so everything
 * is cleaned here: unknown blocks and settings are dropped, texts are cut to
 * size, colours must be hex colours and links must be web addresses. Pictures
 * must be the owner's own uploads (vcard-media/{user id}/…), stored as paths;
 * the API hands them out as full URLs (see forOutput()).
 */
class CardDesign
{
    public const int MAX_BLOCKS = 40;

    public const array FONTS = ['inter', 'space_grotesk', 'poppins', 'montserrat', 'outfit', 'nunito', 'playfair_display', 'lora', 'dm_serif_display', 'roboto_slab'];

    public const array CORNERS = ['round', 'soft', 'square'];

    /**
     * Shapes drawn behind the card in the accent and secondary colours.
     */
    public const array DECORATIONS = ['none', 'circles', 'dots', 'stripes'];

    /**
     * Theme colours with the value used when a design leaves one out.
     */
    public const array THEME_COLORS = [
        'background' => '#ffffff',
        'surface' => '#f5f5f4',
        'text' => '#18181b',
        'muted' => '#57534e',
        'accent' => '#1d4ed8',
        'accent_text' => '#ffffff',
        'secondary' => '#f97316',
        'line' => '#e7e5e4',
    ];

    public const array HEADER_LAYOUTS = ['band', 'tinted', 'centered', 'bold', 'split', 'cover', 'side'];

    public const array QUICK_ACTIONS = ['call', 'email', 'route', 'share', 'whatsapp'];

    /**
     * Every block type with its settings: [kind, default, …options].
     * Kinds: bool, enum (options), text (max length), color, image, images (max count), url, video, actions, items.
     */
    public const array BLOCKS = [
        'header' => [
            'layout' => ['enum', 'band', ...self::HEADER_LAYOUTS],
            'show_photo' => ['bool', true],
            'photo_shape' => ['enum', 'circle', 'circle', 'rounded', 'square'],
            'photo_size' => ['enum', 'm', 's', 'm', 'l'],
            'show_logo' => ['bool', true],
            'logo' => ['image'],
            'cover' => ['image'],
            'name_size' => ['enum', 'm', 's', 'm', 'l', 'xl'],
            'name_color' => ['color'],
            'title_color' => ['color'],
            'show_title' => ['bool', true],
            'show_bio' => ['bool', true],
        ],
        'quick_actions' => [
            'actions' => ['actions', ['call', 'email', 'route', 'share']],
            'shape' => ['enum', 'circle', 'circle', 'rounded', 'square'],
        ],
        'save_contact' => [
            'label' => ['text', 40],
            'variant' => ['enum', 'filled', 'filled', 'outline'],
            'show_wallets' => ['bool', true],
            'show_qr' => ['bool', false],
        ],
        'contact' => [
            'show_email' => ['bool', true],
            'show_phone' => ['bool', true],
            'show_address' => ['bool', true],
            'show_website' => ['bool', false],
            'layout' => ['enum', 'list', 'list', 'boxes'],
            'show_icons' => ['bool', true],
            'dividers' => ['bool', true],
        ],
        'location' => [
            'height' => ['enum', 'm', 's', 'm', 'l'],
        ],
        'opening_hours' => [],
        'buttons' => [
            'variant' => ['enum', 'outline', 'filled', 'outline'],
        ],
        'reviews' => [],
        'social' => [
            'display' => ['enum', 'chips', 'chips', 'icons', 'circles', 'plain'],
        ],
        'text' => [
            'heading' => ['text', 80],
            'body' => ['text', 1500],
        ],
        'image' => [
            'url' => ['image'],
            'caption' => ['text', 140],
            'fit' => ['enum', 'cover', 'cover', 'contain'],
            'height' => ['enum', 'm', 's', 'm', 'l'],
        ],
        'gallery' => [
            'images' => ['images', 9],
            'columns' => ['enum', '2', '2', '3'],
        ],
        'video' => [
            'url' => ['video'],
            'caption' => ['text', 140],
        ],
        'list' => [
            'heading' => ['text', 80],
            'items' => ['items', 20],
        ],
        'link_button' => [
            'label' => ['text', 40],
            'url' => ['url'],
            'variant' => ['enum', 'filled', 'filled', 'outline'],
        ],
        'qr' => [
            'size' => ['enum', 'm', 's', 'm', 'l'],
            'caption' => ['text', 80],
        ],
        'divider' => [],
        'spacer' => [
            'size' => ['enum', 'm', 's', 'm', 'l'],
        ],
    ];

    /**
     * @var (callable(string): ?string)|null
     */
    private $importImage = null;

    public function __construct(private User $user) {}

    /**
     * Pictures that are not the owner's own uploads yet (a website's logo, an
     * attachment) are given to $import, which stores them and returns the path,
     * or null to leave the picture out. Without it such pictures are dropped.
     *
     * @param  callable(string): ?string  $import
     */
    public function importingImagesWith(callable $import): self
    {
        $this->importImage = $import;

        return $this;
    }

    /**
     * A clean design, or null when there is no usable design (the card then uses its template).
     *
     * @return array{theme: array<string, string>, blocks: list<array<string, mixed>>}|null
     */
    public function clean(mixed $design): ?array
    {
        if (is_string($design)) {
            $design = json_decode($design, true);
        }
        if (! is_array($design) || ! is_array($design['blocks'] ?? null)) {
            return null;
        }

        $blocks = [];
        $ids = [];
        foreach (array_slice(array_values($design['blocks']), 0, self::MAX_BLOCKS) as $block) {
            $clean = is_array($block) ? $this->block($block) : null;
            if ($clean === null) {
                continue;
            }
            // Ids keep blocks apart in the editor; a missing or repeated one gets a new one.
            if (isset($ids[$clean['id']])) {
                $clean['id'] = Str::lower(Str::random(8));
            }
            $ids[$clean['id']] = true;
            $blocks[] = $clean;
        }

        return $blocks === [] ? null : ['theme' => $this->theme(is_array($design['theme'] ?? null) ? $design['theme'] : []), 'blocks' => $blocks];
    }

    /**
     * The design as the API hands it out: picture paths turned into full URLs.
     *
     * @param  array<string, mixed>|null  $design
     * @return array<string, mixed>|null
     */
    public static function forOutput(?array $design): ?array
    {
        if ($design === null || ! is_array($design['blocks'] ?? null)) {
            return null;
        }

        $url = fn (mixed $path): ?string => is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
        $design['blocks'] = array_map(function (array $block) use ($url): array {
            foreach (self::BLOCKS[$block['type']] ?? [] as $name => $rule) {
                if ($rule[0] === 'image') {
                    $block[$name] = $url($block[$name] ?? null);
                } elseif ($rule[0] === 'images') {
                    $block[$name] = array_values(array_filter(array_map($url, $block[$name] ?? [])));
                }
            }

            return $block;
        }, $design['blocks']);

        return $design;
    }

    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, string>
     */
    private function theme(array $theme): array
    {
        $clean = [];
        foreach (self::THEME_COLORS as $name => $default) {
            $clean[$name] = self::color($theme[$name] ?? null) ?? $default;
        }
        $clean['heading_font'] = in_array($theme['heading_font'] ?? null, self::FONTS, true) ? $theme['heading_font'] : 'inter';
        $clean['body_font'] = in_array($theme['body_font'] ?? null, self::FONTS, true) ? $theme['body_font'] : 'inter';
        $clean['corners'] = in_array($theme['corners'] ?? null, self::CORNERS, true) ? $theme['corners'] : 'round';
        $clean['decoration'] = in_array($theme['decoration'] ?? null, self::DECORATIONS, true) ? $theme['decoration'] : 'none';

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private function block(array $block): ?array
    {
        $type = $block['type'] ?? null;
        if (! is_string($type) || ! isset(self::BLOCKS[$type])) {
            return null;
        }

        $id = is_string($block['id'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $block['id']) === 1 ? $block['id'] : Str::lower(Str::random(8));
        $clean = ['id' => $id, 'type' => $type, 'hidden' => ($block['hidden'] ?? false) === true];

        foreach (self::BLOCKS[$type] as $name => $rule) {
            $clean[$name] = $this->setting($rule, $block[$name] ?? null);
        }

        $style = is_array($block['style'] ?? null) ? $block['style'] : [];
        $clean['style'] = [
            'background' => self::color($style['background'] ?? null),
            'text' => self::color($style['text'] ?? null),
            'align' => in_array($style['align'] ?? null, ['start', 'center'], true) ? $style['align'] : null,
            'spacing' => in_array($style['spacing'] ?? null, ['compact', 'normal', 'roomy'], true) ? $style['spacing'] : 'normal',
            'boxed' => ($style['boxed'] ?? false) === true,
        ];

        return $clean;
    }

    /**
     * @param  array<int, mixed>  $rule
     */
    private function setting(array $rule, mixed $value): mixed
    {
        return match ($rule[0]) {
            'bool' => is_bool($value) ? $value : $rule[1],
            'enum' => in_array((string) $value, array_slice($rule, 2), true) && $value !== null ? (string) $value : $rule[1],
            'text' => is_string($value) ? self::text($value, $rule[1]) : '',
            'color' => self::color($value),
            'image' => is_string($value) ? $this->image($value) : null,
            'images' => is_array($value) ? array_slice(array_values(array_filter(array_map(fn (mixed $image): ?string => is_string($image) ? $this->image($image) : null, $value))), 0, $rule[1]) : [],
            'url' => is_string($value) ? self::webUrl($value) : null,
            'video' => is_string($value) ? self::videoUrl($value) : null,
            'actions' => is_array($value) ? array_values(array_unique(array_intersect($value, self::QUICK_ACTIONS))) : $rule[1],
            'items' => is_array($value) ? $this->items($value, $rule[1]) : [],
            default => null,
        };
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array{title: string, text: string, price: string}>
     */
    private function items(array $items, int $max): array
    {
        $clean = [];
        foreach (array_slice(array_values($items), 0, $max) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $title = self::text((string) ($item['title'] ?? ''), 80);
            if ($title !== '') {
                $clean[] = ['title' => $title, 'text' => self::text((string) ($item['text'] ?? ''), 300), 'price' => self::text((string) ($item['price'] ?? ''), 30)];
            }
        }

        return $clean;
    }

    /**
     * A picture path in the owner's own media folder. Anything else is imported when an importer is set.
     */
    private function image(string $value): ?string
    {
        $value = trim($value);
        $folder = 'vcard-media/'.$this->user->id.'/';
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';

        $path = str_starts_with($value, $base) ? substr($value, strlen($base)) : $value;
        if (str_starts_with($path, $folder) && preg_match('#^vcard-media/\d+/[A-Za-z0-9-]+\.webp$#', $path) === 1) {
            return $path;
        }

        return $this->importImage !== null && $value !== '' ? ($this->importImage)($value) : null;
    }

    private static function text(string $value, int $max): string
    {
        // Control characters other than line breaks are removed; HTML stays plain text when shown.
        return mb_substr(trim((string) preg_replace('/[^\P{C}\n]/u', '', $value)), 0, $max);
    }

    public static function color(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) === 1 ? strtolower($value) : null;
    }

    public static function webUrl(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = preg_match('~^https?://~i', $value) === 1 ? $value : 'https://'.$value;

        return mb_strlen($value) <= 2000 && filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('~^https?://[^\s]+$~i', $value) === 1 ? $value : null;
    }

    /**
     * Only YouTube and Vimeo videos, which the card shows in their privacy-friendly players.
     */
    public static function videoUrl(string $value): ?string
    {
        $url = self::webUrl($value);

        return $url !== null && preg_match('~^https://(www\.|m\.)?(youtube\.com/(watch\?v=|shorts/|embed/)|youtu\.be/|(player\.)?vimeo\.com/(video/)?)[A-Za-z0-9_-]+~i', $url) === 1 ? $url : null;
    }
}
