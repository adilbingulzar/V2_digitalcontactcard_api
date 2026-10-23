<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Business;

use App\Enums\EmployeeStatus;
use App\Models\BusinessRole;
use App\Models\Employee;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JoinRequestControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_accepts_a_join_request_with_a_role(): void
    {
        $request = Employee::factory()->pending()->create(['requested_by' => 'employee']);
        $role = BusinessRole::factory()->for($request->business)->create();
        Sanctum::actingAs($request->business->owner);

        $this->postJson(route('api.v1.me.business.employees.accept', $request), ['business_role_id' => $role->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.role.id', $role->id);

        $this->assertSame(1, UserNotification::where('user_id', $request->user_id)->where('type', 'accept_join_request')->count());
    }

    public function test_owner_rejects_a_join_request_with_a_reason(): void
    {
        $request = Employee::factory()->pending()->create(['requested_by' => 'employee']);
        Sanctum::actingAs($request->business->owner);

        $this->postJson(route('api.v1.me.business.employees.reject', $request), ['reason' => 'No vacancies'])->assertOk();

        $this->assertSame(EmployeeStatus::Rejected, $request->refresh()->status);
        $this->assertSame('No vacancies', $request->reason);
    }

    public function test_owner_cannot_accept_their_own_invitation_on_behalf_of_the_user(): void
    {
        $invitation = Employee::factory()->pending()->create(['requested_by' => 'business']);
        Sanctum::actingAs($invitation->business->owner);

        $this->postJson(route('api.v1.me.business.employees.accept', $invitation))->assertUnprocessable();

        $this->assertSame(EmployeeStatus::Pending, $invitation->refresh()->status);
    }
}
