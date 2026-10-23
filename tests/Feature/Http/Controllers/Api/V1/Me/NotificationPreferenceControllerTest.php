<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Actions\Notifications\NotifyUser;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPreferenceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_every_kind_is_on_until_the_user_changes_it(): void
    {
        $user = User::factory()->create();
        NotificationPreference::factory()->for($user)->create(['event' => 'service_review', 'is_enabled' => false]);
        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.v1.me.notification-preferences.show'))->assertOk();

        $this->assertCount(count(NotifyUser::EVENTS), $response->json('data'));
        $preferences = collect($response->json('data'))->keyBy('event');
        $this->assertTrue($preferences['service_tip_self']['enabled']);
        $this->assertFalse($preferences['service_review']['enabled']);
        $this->assertSame([], $preferences['service_rating']['stars']);
    }

    public function test_saves_switches_and_rating_star_filter(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.me.notification-preferences.update'), ['preferences' => [
            ['event' => 'service_rating', 'enabled' => true, 'stars' => [2, 1]],
            ['event' => 'service_tip_group', 'enabled' => false],
        ]])->assertOk()->assertJsonPath('data.2.stars', [1, 2]);

        $this->assertSame('1,2', NotificationPreference::whereBelongsTo($user)->where('event', 'service_rating')->value('channel'));
        $this->assertFalse(app(NotifyUser::class)->wants($user->id, 'service_tip_group'));
        $this->assertFalse(app(NotifyUser::class)->wants($user->id, 'service_rating', 5));
        $this->assertTrue(app(NotifyUser::class)->wants($user->id, 'service_rating', 1));
    }

    public function test_all_five_stars_is_stored_as_no_filter(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.me.notification-preferences.update'), ['preferences' => [
            ['event' => 'service_rating', 'enabled' => true, 'stars' => [1, 2, 3, 4, 5]],
        ]])->assertOk();

        $this->assertNull(NotificationPreference::whereBelongsTo($user)->value('channel'));
    }

    public function test_rejects_unknown_kinds(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.notification-preferences.update'), ['preferences' => [
            ['event' => 'newsletter', 'enabled' => true],
        ]])->assertUnprocessable()->assertJsonValidationErrors('preferences.0.event');
    }
}
