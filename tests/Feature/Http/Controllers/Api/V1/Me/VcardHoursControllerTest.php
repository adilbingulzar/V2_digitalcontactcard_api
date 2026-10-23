<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\Business;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VcardHoursControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_replaces_opening_hours_without_touching_shifts(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        WorkingHour::create(['purpose' => 'vcard', 'user_id' => $user->id, 'business_id' => $business->id, 'day' => 'Sun', 'time_ranges' => [['from' => '10:00', 'to' => '12:00']], 'is_active' => true]);
        $shift = WorkingHour::create(['purpose' => 'shift', 'user_id' => $user->id, 'business_id' => $business->id, 'day' => 'Mon', 'time_ranges' => [['from' => '08:00', 'to' => '16:00']], 'is_active' => true]);
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.me.vcard-hours.update'), ['days' => ['Mon' => [['from' => '09:00', 'to' => '17:00']]]])
            ->assertOk()
            ->assertJsonPath('data.Mon', [['from' => '09:00', 'to' => '17:00']])
            ->assertJsonPath('data.Sun', []);

        $this->assertModelExists($shift);
    }
}
