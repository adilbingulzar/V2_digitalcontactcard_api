<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Business;

use App\Models\Employee;
use App\Models\Spot;
use App\Models\SpotEmployee;
use App\Models\WorkingHour;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeShiftControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_replaces_a_members_weekly_shifts(): void
    {
        $employee = Employee::factory()->create();
        WorkingHour::factory()->for($employee->user)->create(['purpose' => 'shift', 'business_id' => $employee->business_id, 'day' => 'Sun']);
        Sanctum::actingAs($employee->business->owner);

        $this->putJson(route('api.v1.me.business.employees.shifts.update', $employee), [
            'days' => ['Mon' => [['from' => '09:00', 'to' => '17:00']], 'Fri' => [['from' => '22:00', 'to' => '02:00']]],
        ])->assertOk()
            ->assertJsonPath('data.Mon', [['from' => '09:00', 'to' => '17:00']])
            ->assertJsonPath('data.Fri', [['from' => '22:00', 'to' => '02:00']])
            ->assertJsonPath('data.Sun', []);

        $this->assertSame(2, WorkingHour::where('purpose', 'shift')->count());
    }

    public function test_rejects_unknown_days_and_bad_times(): void
    {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->business->owner);

        $this->putJson(route('api.v1.me.business.employees.shifts.update', $employee), ['days' => ['Funday' => []]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['days']);
        $this->putJson(route('api.v1.me.business.employees.shifts.update', $employee), ['days' => ['Mon' => [['from' => '25:00', 'to' => '26:00']]]])
            ->assertJsonValidationErrors(['days.Mon.0.from']);
    }

    public function test_owner_sets_spot_shifts_for_a_member_of_their_spot_only(): void
    {
        $member = SpotEmployee::factory()->create();
        $foreignMember = SpotEmployee::factory()->create();
        Sanctum::actingAs($member->spot->business->owner);

        $this->putJson(route('api.v1.me.business.spot-staff.shifts.update', $member), ['days' => ['Sat' => [['from' => '12:00', 'to' => '20:00']]]])
            ->assertOk();
        $this->putJson(route('api.v1.me.business.spot-staff.shifts.update', $foreignMember), ['days' => []])->assertNotFound();

        $this->assertSame($member->spot_id, WorkingHour::where('purpose', 'spot')->sole()->spot_id);
        $this->assertInstanceOf(Spot::class, $foreignMember->spot);
    }
}
