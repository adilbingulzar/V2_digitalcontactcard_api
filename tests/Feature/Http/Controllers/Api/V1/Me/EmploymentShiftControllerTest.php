<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\Employee;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmploymentShiftControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_employee_sets_their_own_working_hours_and_the_business_sees_them(): void
    {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->user);

        $this->putJson(route('api.v1.me.employments.shifts.update', $employee), [
            'days' => ['Tue' => [['from' => '10:00', 'to' => '18:00']]],
        ])->assertOk()
            ->assertJsonPath('data.Tue', [['from' => '10:00', 'to' => '18:00']]);

        $this->getJson(route('api.v1.me.employments.shifts.show', $employee))->assertJsonPath('data.Tue.0.from', '10:00');

        Sanctum::actingAs($employee->business->owner);
        $this->getJson(route('api.v1.me.business.employees.shifts.show', $employee))->assertJsonPath('data.Tue.0.to', '18:00');
    }

    public function test_someone_elses_employment_is_not_found(): void
    {
        $employee = Employee::factory()->create();
        Sanctum::actingAs(Employee::factory()->create()->user);

        $this->getJson(route('api.v1.me.employments.shifts.show', $employee))->assertNotFound();
    }

    public function test_a_pending_member_cannot_set_hours_yet(): void
    {
        $employee = Employee::factory()->pending()->create();
        Sanctum::actingAs($employee->user);

        $this->putJson(route('api.v1.me.employments.shifts.update', $employee), ['days' => []])->assertForbidden();
    }
}
