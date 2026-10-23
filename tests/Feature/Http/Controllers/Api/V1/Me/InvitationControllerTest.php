<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvitationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_accepts_an_invitation_and_the_owner_is_told(): void
    {
        $invitation = Employee::factory()->pending()->create(['requested_by' => 'business']);
        Sanctum::actingAs($invitation->user);

        $this->postJson(route('api.v1.me.employments.accept', $invitation))->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->assertSame(1, UserNotification::where('user_id', $invitation->business->owner_id)->where('type', 'accept_join_request')->count());
    }

    public function test_user_declines_an_invitation(): void
    {
        $invitation = Employee::factory()->pending()->create(['requested_by' => 'business']);
        Sanctum::actingAs($invitation->user);

        $this->postJson(route('api.v1.me.employments.decline', $invitation))->assertOk();

        $this->assertSame(EmployeeStatus::Rejected, $invitation->refresh()->status);
    }

    public function test_user_cannot_accept_their_own_join_request(): void
    {
        $request = Employee::factory()->pending()->create(['requested_by' => 'employee']);
        Sanctum::actingAs($request->user);

        $this->postJson(route('api.v1.me.employments.accept', $request))->assertUnprocessable();

        $this->assertSame(EmployeeStatus::Pending, $request->refresh()->status);
    }

    public function test_returns_404_for_someone_elses_invitation(): void
    {
        $invitation = Employee::factory()->pending()->create(['requested_by' => 'business']);
        Sanctum::actingAs(Employee::factory()->create()->user);

        $this->postJson(route('api.v1.me.employments.accept', $invitation))->assertNotFound();
    }
}
