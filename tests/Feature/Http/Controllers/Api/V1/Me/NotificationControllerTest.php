<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_own_notifications_newest_first_with_unread_count(): void
    {
        $user = User::factory()->create();
        $older = UserNotification::factory()->for($user)->create(['read_at' => now()]);
        $newer = UserNotification::factory()->for($user)->create();
        UserNotification::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.notifications.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$newer->id, $older->id])
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('meta.unread_count', 1);
    }

    public function test_marks_one_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = UserNotification::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.me.notifications.read', $notification))
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_read_someone_elses_notification(): void
    {
        $notification = UserNotification::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.me.notifications.read', $notification))->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_marks_all_own_notifications_as_read(): void
    {
        $user = User::factory()->create();
        UserNotification::factory()->count(2)->for($user)->create();
        $someoneElses = UserNotification::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.me.notifications.read-all'))->assertNoContent();

        $this->assertSame(0, UserNotification::whereBelongsTo($user)->unread()->count());
        $this->assertNull($someoneElses->fresh()->read_at);
    }
}
