<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Models\PaymentMethod;
use App\Models\Tip;
use App\Models\TipPageSetting;
use App\Models\User;
use App\Models\VisitorLog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shows_the_public_page_without_private_details(): void
    {
        $user = User::factory()->create(['slug' => 'anna', 'name' => 'Anna', 'phone' => '612345678', 'address' => 'Secret street 1']);
        TipPageSetting::factory()->for($user)->create(['default_page' => TipPageSetting::PAGE_TIP_ONLY, 'preset_amounts' => [2, 4]]);
        Tip::factory()->rating()->for($user, 'recipient')->create(['rating' => 4]);
        Tip::factory()->rating()->for($user, 'recipient')->create(['rating' => 5]);

        $response = $this->getJson(route('api.v1.public.profiles.show', 'anna'));

        $response->assertOk()
            ->assertJsonPath('data.name', 'Anna')
            ->assertJsonPath('data.can_receive_tips', true)
            ->assertJsonPath('data.page.mode', 'tip')
            ->assertJsonPath('data.page.preset_amounts', ['2.00', '4.00'])
            ->assertJsonPath('data.rating', ['average' => 4.5, 'count' => 2]);
        $this->assertStringNotContainsString($user->email, $response->getContent());
        $this->assertStringNotContainsString('612345678', $response->getContent());
        $this->assertStringNotContainsString('Secret street', $response->getContent());
    }

    public function test_lists_only_the_payment_methods_the_user_offers(): void
    {
        $user = User::factory()->create(['slug' => 'anna']);
        PaymentMethod::factory()->for($user)->create(['pay_option_ids' => [10, 436]]);

        $this->getJson(route('api.v1.public.profiles.show', 'anna'))
            ->assertJsonPath('data.tips.payment_methods.*.id', [10, 436]);
    }

    public function test_records_the_visit(): void
    {
        User::factory()->create(['slug' => 'anna']);

        $this->getJson(route('api.v1.public.profiles.show', ['user' => 'anna', 'page' => 'review']))->assertOk();

        $this->assertSame('review', VisitorLog::sole()->page_type);
    }

    public function test_returns_404_for_a_deleted_user(): void
    {
        User::factory()->create(['slug' => 'gone'])->delete();

        $this->getJson(route('api.v1.public.profiles.show', 'gone'))->assertNotFound();
    }
}
