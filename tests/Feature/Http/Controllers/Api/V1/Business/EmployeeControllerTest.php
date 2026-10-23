<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Business;

use App\Enums\EmployeeStatus;
use App\Models\Agreement;
use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\Employee;
use App\Models\Spot;
use App\Models\SpotEmployee;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WorkingHour;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_invites_a_user_by_email_with_the_default_agreement(): void
    {
        $business = Business::factory()->create();
        $agreement = Agreement::factory()->for($business)->create();
        $business->update(['default_agreement_id' => $agreement->id]);
        $waiter = User::factory()->create(['email' => 'waiter@example.com']);
        Sanctum::actingAs($business->owner);

        $this->postJson(route('api.v1.me.business.employees.store'), ['identifier' => 'Waiter@Example.com'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requested_by', 'business')
            ->assertJsonPath('data.agreement.id', $agreement->id);

        $this->assertSame(1, UserNotification::whereBelongsTo($waiter)->where('type', 'join_request')->count());
    }

    public function test_cannot_invite_someone_already_in_the_team_or_unknown(): void
    {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->business->owner);

        $this->postJson(route('api.v1.me.business.employees.store'), ['identifier' => $employee->user->slug])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identifier' => 'This person is already in your team.']);
        $this->postJson(route('api.v1.me.business.employees.store'), ['identifier' => 'nobody@example.com'])
            ->assertJsonValidationErrors(['identifier' => 'No active account was found with this email or public name.']);
    }

    public function test_role_must_belong_to_the_business(): void
    {
        $employee = Employee::factory()->create();
        Sanctum::actingAs($employee->business->owner);

        $this->patchJson(route('api.v1.me.business.employees.update', $employee), ['business_role_id' => BusinessRole::factory()->create()->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['business_role_id']);
    }

    public function test_removing_a_member_also_removes_their_spots_and_shifts(): void
    {
        $employee = Employee::factory()->create();
        $spot = Spot::factory()->for($employee->business)->create();
        SpotEmployee::factory()->for($spot)->for($employee->user)->create();
        WorkingHour::factory()->for($employee->user)->create(['purpose' => 'shift', 'business_id' => $employee->business_id]);
        $ownVcardHours = WorkingHour::factory()->for($employee->user)->create(['purpose' => 'vcard']);
        Sanctum::actingAs($employee->business->owner);

        $this->deleteJson(route('api.v1.me.business.employees.destroy', $employee))->assertNoContent();

        $this->assertModelMissing($employee);
        $this->assertSame(0, SpotEmployee::count());
        $this->assertSame([$ownVcardHours->id], WorkingHour::pluck('id')->all());
    }

    public function test_returns_404_for_a_member_of_another_business(): void
    {
        $foreign = Employee::factory()->create();
        Sanctum::actingAs(Business::factory()->create()->owner);

        $this->patchJson(route('api.v1.me.business.employees.update', $foreign), ['business_role_id' => null])->assertNotFound();
        $this->deleteJson(route('api.v1.me.business.employees.destroy', $foreign))->assertNotFound();

        $this->assertSame(EmployeeStatus::Accepted, $foreign->refresh()->status);
    }
}
