<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Admin;

use App\Actions\Subscriptions\SubscriptionStatus;
use App\Models\AppSetting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_granted_plan_lets_the_user_transfer_and_can_be_ended(): void
    {
        AppSetting::current()->update(['user_subscription_required' => true, 'free_trial_days' => 0]);
        $user = User::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->assertFalse(app(SubscriptionStatus::class)->allowsPayout($user));

        $this->postJson(route('api.v1.admin.subscriptions.store'), ['user_id' => $user->id, 'months' => 3])
            ->assertCreated()
            ->assertJsonPath('data.starts_on', today()->toDateString())
            ->assertJsonPath('data.ends_on', today()->addMonths(3)->subDay()->toDateString());

        $status = app(SubscriptionStatus::class)->for($user);
        $this->assertTrue($status['allows_payout']);
        $this->assertTrue($status['granted']);
        $this->getJson(route('api.v1.admin.subscriptions.index'))->assertOk()->assertJsonPath('data.0.plan', 'free');

        $this->deleteJson(route('api.v1.admin.subscriptions.destroy', Subscription::sole()))->assertNoContent();
        $this->assertFalse(app(SubscriptionStatus::class)->allowsPayout($user));
    }
}
