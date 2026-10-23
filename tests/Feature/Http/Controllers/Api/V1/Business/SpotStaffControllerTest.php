<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Business;

use App\Models\BusinessRole;
use App\Models\Employee;
use App\Models\Spot;
use App\Models\SpotEmployee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpotStaffControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sets_the_team_members_working_at_a_spot(): void
    {
        $spot = Spot::factory()->create();
        $role = BusinessRole::factory()->for($spot->business)->create();
        $stays = Employee::factory()->for($spot->business)->create();
        $joins = Employee::factory()->for($spot->business)->create();
        $leaves = Employee::factory()->for($spot->business)->create();
        SpotEmployee::factory()->for($spot)->create(['user_id' => $stays->user_id, 'is_live' => true]);
        SpotEmployee::factory()->for($spot)->create(['user_id' => $leaves->user_id]);
        Sanctum::actingAs($spot->business->owner);

        $this->putJson(route('api.v1.me.business.spots.staff.update', $spot), ['members' => [
            ['user_id' => $stays->user_id, 'business_role_id' => $role->id],
            ['user_id' => $joins->user_id, 'business_role_id' => $role->id],
        ]])->assertOk()->assertJsonCount(2, 'data.staff');

        $this->assertEqualsCanonicalizing([$stays->user_id, $joins->user_id], $spot->spotEmployees()->pluck('user_id')->all());
        $this->assertTrue($spot->spotEmployees()->where('user_id', $stays->user_id)->value('is_live'));
    }

    public function test_only_team_members_can_work_at_a_spot(): void
    {
        $spot = Spot::factory()->create();
        $role = BusinessRole::factory()->for($spot->business)->create();
        Sanctum::actingAs($spot->business->owner);

        $this->putJson(route('api.v1.me.business.spots.staff.update', $spot), ['members' => [
            ['user_id' => User::factory()->create()->id, 'business_role_id' => $role->id],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['members.0.user_id' => 'Only members of your team can work at a spot.']);
    }
}
