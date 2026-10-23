<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\TipPageSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipPageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shows_defaults_when_the_page_was_never_customised(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.me.tip-page.show'))
            ->assertOk()
            ->assertJsonPath('data.mode', 'tip_and_review')
            ->assertJsonPath('data.button_style', 'orange')
            ->assertJsonPath('data.preset_amounts', ['5.00', '10.00', '15.00']);
    }

    public function test_saves_the_page_and_the_public_page_shows_it(): void
    {
        $user = User::factory()->create(['slug' => 'anna']);
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.me.tip-page.update'), $this->payload())
            ->assertOk()
            ->assertJsonPath('data.mode', 'tip')
            ->assertJsonPath('data.preset_amounts', ['2.50', '5.00', '7.50']);

        $this->getJson(route('api.v1.public.profiles.show', 'anna'))
            ->assertJsonPath('data.page.mode', 'tip')
            ->assertJsonPath('data.page.theme.button_style', 'red')
            ->assertJsonPath('data.page.theme.page_color', '#112233');
    }

    public function test_reads_button_styles_stored_by_the_old_app(): void
    {
        $user = User::factory()->create();
        TipPageSetting::factory()->for($user)->create(['button_color' => 'custom-btn btn-info shadow-0 text-white']);
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.tip-page.show'))->assertJsonPath('data.button_style', 'blue');
    }

    public function test_rejects_colours_that_are_not_hex_codes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.tip-page.update'), $this->payload(['page_color' => 'red;background:url(x)']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['page_color' => 'Use a colour code like #F97316.']);
    }

    public function test_needs_exactly_three_preset_amounts_within_the_limits(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.tip-page.update'), $this->payload(['preset_amounts' => [1, 2]]))
            ->assertJsonValidationErrors(['preset_amounts']);
        $this->putJson(route('api.v1.me.tip-page.update'), $this->payload(['preset_amounts' => [1, 2, 9999]]))
            ->assertJsonValidationErrors(['preset_amounts.2']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function test_saves_sticky_reviews_review_style_pattern_and_payment_methods(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.me.tip-page.update'), $this->payload([
            'pin_reviews' => true,
            'review_style' => 2,
            'pattern' => 'pattern2',
            'payment_methods' => [10, 706],
        ]))
            ->assertOk()
            ->assertJsonPath('data.pin_reviews', true)
            ->assertJsonPath('data.review_style', 2)
            ->assertJsonPath('data.pattern', 'pattern2')
            ->assertJsonPath('data.background_image', config('tiqs.frontend_url').'/images/pattern/pattern2.png')
            ->assertJsonPath('data.payment_methods.0', ['id' => 10, 'name' => 'iDEAL', 'icon' => 'ideal', 'chosen' => true])
            ->assertJsonPath('data.payment_methods.2.chosen', false);

        $this->assertSame([10, 706], array_keys($user->fresh()->offeredPaymentMethods()));

        // Clearing the pattern removes it; saving without the key leaves it.
        $this->putJson(route('api.v1.me.tip-page.update'), $this->payload(['pattern' => null]))->assertJsonPath('data.background_image', null);
    }

    public function test_rejects_unknown_patterns_and_payment_methods(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.tip-page.update'), $this->payload(['pattern' => '../secret', 'payment_methods' => [99999]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pattern', 'payment_methods.0']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'mode' => 'tip',
            'header' => 'Thanks for visiting',
            'title' => 'Rate us',
            'success_header' => 'Thank you!',
            'success_message' => 'See you soon',
            'page_color' => '#112233',
            'panel_color' => '#FFFFFF',
            'text_color' => null,
            'emoji_color' => null,
            'button_style' => 'red',
            'emoji_type' => 1,
            'show_email_field' => true,
            'show_review_field' => false,
            'preset_amounts' => ['2.5', '5', '7.50'],
        ], $overrides);
    }
}
