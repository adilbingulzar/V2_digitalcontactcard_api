<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\User;
use App\Models\Vcard;
use App\Models\VcardTheme;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VcardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'is_active' => true,
            'theme' => Vcard::THEME_CUSTOM,
            'show_business_name' => true,
            'business_name' => 'Cafe Anna',
            'business_title' => 'Owner',
            'business_email' => 'hi@anna.test',
            'show_location' => true,
            'business_address' => 'Dam 1, Amsterdam',
            'show_opening_hours' => true,
            'show_review' => true,
            'show_business_icon' => true,
            'show_email_icon' => true,
            'show_phone_icon' => false,
            'show_address_icon' => true,
            'social_links' => ['https://instagram.com/anna'],
            'appointment_url' => 'https://cal.example.com/anna',
            'colors' => ['primary_background' => '#112233'],
        ], $overrides);
    }

    public function test_a_new_card_starts_from_the_profile(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.vcard.show'))
            ->assertOk()
            ->assertJsonPath('data.exists', false)
            ->assertJsonPath('data.business_email', 'anna@example.com')
            ->assertJsonPath('data.colors.primary_background', VcardTheme::DEFAULTS['primary_background']);
    }

    public function test_saves_the_card_and_its_colours(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.me.vcard.update'), $this->payload())
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.business_name', 'Cafe Anna')
            ->assertJsonPath('data.show_phone_icon', false)
            ->assertJsonPath('data.colors.primary_background', '#112233');

        $user->refresh();
        $this->assertSame(['https://instagram.com/anna'], $user->vcard->social_links);
        $this->assertSame('#112233', $user->vcardTheme->primary_background);
    }

    public function test_rejects_unsafe_links_and_a_hidden_name_without_logo(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.vcard.update'), $this->payload([
            'show_business_name' => false,
            'social_links' => ['javascript:alert(1)'],
            'appointment_url' => 'ftp://example.com',
            'colors' => ['primary_background' => 'red'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['show_business_name', 'social_links.0', 'appointment_url', 'colors.primary_background']);
    }

    public function test_address_is_required_only_when_the_location_is_shown(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.vcard.update'), $this->payload(['business_address' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['business_address' => 'Enter the address, or hide the location.']);

        $this->putJson(route('api.v1.me.vcard.update'), $this->payload(['business_address' => null, 'show_location' => false]))->assertOk();
    }

    public function test_saves_the_style_and_brand_colour_of_the_card_builder(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.vcard.show'))
            ->assertJsonPath('data.template', Vcard::NEW_TEMPLATE)
            ->assertJsonPath('data.accent_color', Vcard::NEW_ACCENT_COLOR);

        $this->putJson(route('api.v1.me.vcard.update'), $this->payload(['template' => 'minimal', 'accent_color' => '#0F766E']))
            ->assertOk()
            ->assertJsonPath('data.template', 'minimal')
            ->assertJsonPath('data.accent_color', '#0f766e');

        $this->putJson(route('api.v1.me.vcard.update'), $this->payload(['template' => 'fancy', 'accent_color' => 'teal']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['template', 'accent_color']);
    }

    public function test_cards_saved_before_the_builder_get_a_look_from_their_old_theme(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Vcard::factory()->for($user)->create(['theme' => Vcard::THEME_GREEN, 'template' => null, 'accent_color' => null]);

        $this->getJson(route('api.v1.me.vcard.show'))
            ->assertJsonPath('data.template', 'bold')
            ->assertJsonPath('data.accent_color', VcardTheme::CLASSIC['secondary_button']);

        $user->vcard->update(['theme' => Vcard::THEME_CUSTOM]);
        VcardTheme::factory()->for($user)->create(['primary_background' => '#7E22CE']);
        $user->refresh();

        $this->getJson(route('api.v1.me.vcard.show'))
            ->assertJsonPath('data.template', 'classic')
            ->assertJsonPath('data.accent_color', '#7e22ce');
    }

    public function test_saves_the_order_of_the_card_parts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.vcard.show'))->assertJsonPath('data.section_order', Vcard::SECTIONS);

        $this->putJson(route('api.v1.me.vcard.update'), $this->payload(['section_order' => ['save_contact', 'location']]))
            ->assertOk()
            ->assertJsonPath('data.section_order', ['save_contact', 'location', 'quick_actions', 'contact', 'opening_hours', 'buttons', 'reviews', 'social']);

        $this->putJson(route('api.v1.me.vcard.update'), $this->payload(['section_order' => ['location', 'location', 'header']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['section_order.0', 'section_order.2']);
    }
}
