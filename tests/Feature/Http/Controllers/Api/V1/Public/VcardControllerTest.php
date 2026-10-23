<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Enums\PaymentStatus;
use App\Models\Tip;
use App\Models\User;
use App\Models\Vcard;
use App\Models\VcardTheme;
use App\Models\WorkingHour;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VcardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shows_only_what_the_owner_chose_to_show(): void
    {
        $user = User::factory()->create(['slug' => 'anna', 'country_code' => '31', 'phone' => '612345678']);
        Vcard::factory()->for($user)->create([
            'business_name' => 'Cafe Anna',
            'show_location' => false,
            'social_links' => ['https://instagram.com/anna', 'javascript:alert(1)'],
            'appointment_url' => 'javascript:alert(1)',
        ]);

        $this->getJson(route('api.v1.public.vcards.show', 'anna'))
            ->assertOk()
            ->assertJsonPath('data.business.name', 'Cafe Anna')
            ->assertJsonPath('data.business.address', null)
            ->assertJsonPath('data.phone', '+31 612345678')
            ->assertJsonPath('data.theme', 'classic')
            ->assertJsonPath('data.colors.primary_background', VcardTheme::CLASSIC['primary_background'])
            ->assertJsonPath('data.social_links', ['https://instagram.com/anna'])
            ->assertJsonPath('data.links.appointment', null);
    }

    public function test_custom_theme_uses_own_colours_and_shows_opening_hours_and_reviews(): void
    {
        $user = User::factory()->create(['slug' => 'anna']);
        Vcard::factory()->for($user)->create(['theme' => Vcard::THEME_CUSTOM]);
        VcardTheme::factory()->for($user)->create(['primary_background' => '#123456', 'primary_text' => 'red']);
        WorkingHour::create(['purpose' => 'vcard', 'user_id' => $user->id, 'day' => 'Mon', 'time_ranges' => [['from' => '09:00', 'to' => '17:00']], 'is_active' => true]);
        Tip::factory()->for($user, 'recipient')->create(['review' => 'Great service', 'rating' => 5, 'payment_status' => PaymentStatus::Paid]);
        Tip::factory()->for($user, 'recipient')->create(['review' => 'Never paid', 'rating' => 5, 'payment_status' => PaymentStatus::Cancelled]);

        $this->getJson(route('api.v1.public.vcards.show', 'anna'))
            ->assertOk()
            ->assertJsonPath('data.colors.primary_background', '#123456')
            ->assertJsonPath('data.colors.primary_text', VcardTheme::DEFAULTS['primary_text'])
            ->assertJsonPath('data.opening_hours.Mon', [['from' => '09:00', 'to' => '17:00']])
            ->assertJsonPath('data.reviews.*.review', ['Great service']);
    }

    public function test_switched_off_or_missing_cards_are_not_found(): void
    {
        $user = User::factory()->create(['slug' => 'anna']);
        User::factory()->create(['slug' => 'bob']);
        Vcard::factory()->for($user)->create(['is_active' => false]);

        $this->getJson(route('api.v1.public.vcards.show', 'anna'))->assertNotFound();
        $this->getJson(route('api.v1.public.vcards.show', 'bob'))->assertNotFound();
    }

    public function test_finds_the_card_address_of_an_old_numeric_link(): void
    {
        $user = User::factory()->create(['slug' => 'anna']);
        Vcard::factory()->for($user)->create();
        $hidden = User::factory()->create();
        Vcard::factory()->for($hidden)->create(['is_active' => false]);

        $this->getJson(route('api.v1.public.vcards.slug', $user->id))->assertOk()->assertJsonPath('data.slug', 'anna');
        $this->getJson(route('api.v1.public.vcards.slug', $hidden->id))->assertNotFound();
    }
}
