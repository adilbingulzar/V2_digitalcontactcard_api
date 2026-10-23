<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmploymentLiveControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_employee_goes_live_and_offline(): void
    {
        $employee = Employee::factory()->create(['is_live' => false]);
        Sanctum::actingAs($employee->user);

        $this->putJson(route('api.v1.me.employments.live', $employee), ['is_live' => true])
            ->assertOk()
            ->assertJsonPath('data.is_live', true);
        $this->assertNotNull($employee->refresh()->last_live_at);

        $this->putJson(route('api.v1.me.employments.live', $employee), ['is_live' => false])->assertOk();
        $this->assertFalse($employee->refresh()->is_live);
    }

    public function test_returns_404_for_someone_elses_employment(): void
    {
        $employee = Employee::factory()->create(['is_live' => false]);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.employments.live', $employee), ['is_live' => true])->assertNotFound();

        $this->assertFalse($employee->refresh()->is_live);
    }

    public function test_returns_403_before_the_business_accepted_the_employee(): void
    {
        $employee = Employee::factory()->pending()->create();
        Sanctum::actingAs($employee->user);

        $this->putJson(route('api.v1.me.employments.live', $employee), ['is_live' => true])
            ->assertForbidden()
            ->assertJsonPath('message', 'You can go live once the business has accepted you.');
    }
}
