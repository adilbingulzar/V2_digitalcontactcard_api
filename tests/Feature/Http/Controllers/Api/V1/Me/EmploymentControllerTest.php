<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmploymentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_asks_to_join_a_business_by_its_code(): void
    {
        $business = Business::factory()->create(['code' => 'BIZ12345678']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.me.employments.store'), ['business_code' => 'biz12345678'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requested_by', 'employee');

        $this->assertSame(1, UserNotification::where('user_id', $business->owner_id)->where('type', 'join_request')->count());
    }

    public function test_cannot_join_an_unknown_or_own_business(): void
    {
        $business = Business::factory()->create();
        Sanctum::actingAs($business->owner);

        $this->postJson(route('api.v1.me.employments.store'), ['business_code' => 'BIZ00000000'])
            ->assertJsonValidationErrors(['business_code' => 'No business was found with this code.']);
        $this->postJson(route('api.v1.me.employments.store'), ['business_code' => $business->code])
            ->assertJsonValidationErrors(['business_code' => 'This is your own business.']);
    }

    public function test_user_leaves_a_business_but_cannot_remove_someone_else(): void
    {
        $mine = Employee::factory()->create();
        $theirs = Employee::factory()->create();
        Sanctum::actingAs($mine->user);

        $this->deleteJson(route('api.v1.me.employments.destroy', $theirs))->assertNotFound();
        $this->deleteJson(route('api.v1.me.employments.destroy', $mine))->assertNoContent();

        $this->assertModelMissing($mine);
        $this->assertModelExists($theirs);
    }
}
