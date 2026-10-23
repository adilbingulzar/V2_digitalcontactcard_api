<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\Spot;
use App\Models\SpotEmployee;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkSpotControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_only_own_active_spots(): void
    {
        $user = User::factory()->create();
        $mine = SpotEmployee::factory()->for($user)->create();
        SpotEmployee::factory()->for($user)->for(Spot::factory()->create(['is_active' => false]))->create();
        SpotEmployee::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.spots.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$mine->id])
            ->assertJsonPath('data.0.spot.name', $mine->spot->name)
            ->assertJsonPath('data.0.is_live', false);
    }

    public function test_going_live_at_one_spot_ends_live_at_the_others(): void
    {
        $user = User::factory()->create();
        $first = SpotEmployee::factory()->for($user)->create(['is_live' => true]);
        $second = SpotEmployee::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.me.spots.live', $second), ['is_live' => true])->assertOk()->assertJsonPath('data.is_live', true);

        $this->assertFalse($first->fresh()->is_live);
        $this->assertTrue($second->fresh()->is_live);
    }

    public function test_leaving_a_spot_removes_its_shift_hours(): void
    {
        $user = User::factory()->create();
        $assignment = SpotEmployee::factory()->for($user)->create();
        $hours = WorkingHour::create(['purpose' => 'spot', 'user_id' => $user->id, 'business_id' => $assignment->spot->business_id, 'spot_id' => $assignment->spot_id, 'day' => 'Mon', 'time_ranges' => [['from' => '09:00', 'to' => '17:00']], 'is_active' => true]);
        Sanctum::actingAs($user);

        $this->deleteJson(route('api.v1.me.spots.destroy', $assignment))->assertNoContent();

        $this->assertModelMissing($assignment);
        $this->assertModelMissing($hours);
    }

    public function test_cannot_change_someone_elses_spot(): void
    {
        $other = SpotEmployee::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.spots.live', $other), ['is_live' => true])->assertNotFound();
        $this->deleteJson(route('api.v1.me.spots.destroy', $other))->assertNotFound();

        $this->assertModelExists($other);
        $this->assertFalse($other->fresh()->is_live);
    }
}
