<?php

namespace Tests\Feature\Services\CardDesign;

use App\Models\User;
use App\Services\CardDesign\CardDesign;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardDesignTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_keeps_known_blocks_and_settings_and_drops_the_rest(): void
    {
        $user = User::factory()->create();

        $design = (new CardDesign($user))->clean([
            'theme' => ['background' => '#141414', 'accent' => '#FBBF24', 'text' => 'red', 'heading_font' => 'comic_sans', 'body_font' => 'poppins', 'corners' => 'square'],
            'blocks' => [
                ['id' => 'top', 'type' => 'header', 'layout' => 'split', 'name_size' => 'xxl', 'onclick' => 'alert(1)', 'style' => ['background' => '#000000', 'align' => 'center', 'css' => 'x']],
                ['type' => 'script', 'body' => '<script>'],
                ['id' => 'top', 'type' => 'text', 'heading' => str_repeat('a', 100), 'body' => "Line one\nLine two<b>bold</b>"],
                ['type' => 'link_button', 'label' => 'Menu', 'url' => 'javascript:alert(1)'],
                ['type' => 'link_button', 'label' => 'Shop', 'url' => 'shop.example.com'],
                ['type' => 'video', 'url' => 'https://evil.example.com/video'],
                ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=abc123'],
                ['type' => 'quick_actions', 'actions' => ['call', 'hack', 'whatsapp', 'call']],
                ['type' => 'list', 'items' => [['title' => 'Haircut', 'price' => '€25'], ['text' => 'no title']]],
            ],
        ]);

        $this->assertSame(['background' => '#141414', 'surface' => '#f5f5f4', 'text' => '#18181b', 'muted' => '#57534e', 'accent' => '#fbbf24', 'accent_text' => '#ffffff', 'secondary' => '#f97316', 'line' => '#e7e5e4', 'heading_font' => 'inter', 'body_font' => 'poppins', 'corners' => 'square', 'decoration' => 'none'], $design['theme']);
        $this->assertSame(['header', 'text', 'link_button', 'link_button', 'video', 'video', 'quick_actions', 'list'], array_column($design['blocks'], 'type'));

        [$header, $text, $badLink, $goodLink, $badVideo, $goodVideo, $actions, $list] = $design['blocks'];
        $this->assertSame(['split', 'm', 'top'], [$header['layout'], $header['name_size'], $header['id']]);
        $this->assertArrayNotHasKey('onclick', $header);
        $this->assertSame(['background' => '#000000', 'text' => null, 'align' => 'center', 'spacing' => 'normal', 'boxed' => false], $header['style']);
        $this->assertNotSame('top', $text['id']);
        $this->assertSame(80, mb_strlen($text['heading']));
        $this->assertSame("Line one\nLine two<b>bold</b>", $text['body']);
        $this->assertNull($badLink['url']);
        $this->assertSame('https://shop.example.com', $goodLink['url']);
        $this->assertNull($badVideo['url']);
        $this->assertSame('https://www.youtube.com/watch?v=abc123', $goodVideo['url']);
        $this->assertSame(['call', 'whatsapp'], $actions['actions']);
        $this->assertSame([['title' => 'Haircut', 'text' => '', 'price' => '€25']], $list['items']);
    }

    public function test_only_keeps_pictures_from_the_owners_own_media_folder(): void
    {
        $user = User::factory()->create();
        $own = "vcard-media/{$user->id}/0b1c2d3e-4f50-6172-8394-a5b6c7d8e9f0.webp";
        $other = 'vcard-media/'.($user->id + 1).'/0b1c2d3e-4f50-6172-8394-a5b6c7d8e9f0.webp';

        $design = (new CardDesign($user))->clean(['blocks' => [
            ['type' => 'gallery', 'images' => [Storage::disk('public')->url($own), $other, 'https://example.com/cat.jpg', '../../.env']],
            ['type' => 'header', 'logo' => 'https://example.com/logo.png'],
        ]]);

        $this->assertSame([$own], $design['blocks'][0]['images']);
        $this->assertNull($design['blocks'][1]['logo']);
        $this->assertSame([Storage::disk('public')->url($own)], CardDesign::forOutput($design)['blocks'][0]['images']);

        $imported = (new CardDesign($user))->importingImagesWith(fn (string $url): string => $own)->clean(['blocks' => [['type' => 'header', 'logo' => 'https://example.com/logo.png']]]);
        $this->assertSame($own, $imported['blocks'][0]['logo']);
    }

    public function test_a_design_without_blocks_means_the_template(): void
    {
        $user = User::factory()->create();

        $this->assertNull((new CardDesign($user))->clean(['theme' => [], 'blocks' => [['type' => 'nonsense']]]));
        $this->assertNull((new CardDesign($user))->clean('not json'));
        $this->assertSame('header', (new CardDesign($user))->clean('{"blocks":[{"type":"header"}]}')['blocks'][0]['type']);
    }

    public function test_a_setting_never_replaces_the_style_of_a_block(): void
    {
        foreach (CardDesign::BLOCKS as $type => $settings) {
            $this->assertArrayNotHasKey('style', $settings, "{$type} has a setting named style");
            $this->assertArrayNotHasKey('hidden', $settings, "{$type} has a setting named hidden");
        }

        $design = (new CardDesign(User::factory()->create()))->clean(['blocks' => [['type' => 'social', 'display' => 'icons', 'style' => ['boxed' => true]]]]);
        $this->assertSame('icons', $design['blocks'][0]['display']);
        $this->assertTrue($design['blocks'][0]['style']['boxed']);
    }

    public function test_keeps_colour_settings_only_as_hex_colours(): void
    {
        $design = (new CardDesign(User::factory()->create()))->clean([
            'theme' => ['secondary' => '#EF624B', 'decoration' => 'circles'],
            'blocks' => [['type' => 'header', 'layout' => 'side', 'name_color' => '#19849A', 'title_color' => 'teal'], ['type' => 'qr', 'size' => 'l'], ['type' => 'save_contact', 'show_qr' => true]],
        ]);

        $this->assertSame(['#ef624b', 'circles'], [$design['theme']['secondary'], $design['theme']['decoration']]);
        $this->assertSame(['side', '#19849a', null], [$design['blocks'][0]['layout'], $design['blocks'][0]['name_color'], $design['blocks'][0]['title_color']]);
        $this->assertSame('l', $design['blocks'][1]['size']);
        $this->assertTrue($design['blocks'][2]['show_qr']);
    }
}
