<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Actions\Tips\QuoteTip;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayoutSettingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_saves_schedule_and_who_pays_the_platform_fee(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.me.payout-settings.update'), ['schedule' => 'weekly', 'platform_fee_paid_by' => 'customer'])->assertOk();

        $this->getJson(route('api.v1.me.payouts.show'))
            ->assertJsonPath('data.settings', ['schedule' => 'weekly', 'platform_fee_paid_by' => 'customer']);
        $this->assertTrue(app(QuoteTip::class)->handle($user, 1000, 10)->customerPaysPlatformFee);
    }

    public function test_rejects_an_unknown_schedule(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.payout-settings.update'), ['schedule' => 'hourly', 'platform_fee_paid_by' => 'recipient'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['schedule']);
    }
}
