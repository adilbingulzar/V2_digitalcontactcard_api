<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountStatusControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_user_can_deactivate_and_reactivate_their_account(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.me.account.status'), ['status' => 'deactivated'])
            ->assertOk()
            ->assertJsonPath('data.status', 'deactivated');
        $this->assertSame(UserStatus::Deactivated, $user->fresh()->status);

        $this->putJson(route('api.v1.me.account.status'), ['status' => 'active'])->assertOk();
        $this->assertSame(UserStatus::Active, $user->fresh()->status);
    }

    public function test_only_active_and_deactivated_are_allowed(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.account.status'), ['status' => 'pending'])->assertUnprocessable();
    }

    public function test_an_unconfirmed_account_cannot_make_itself_active(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Pending, 'email_verified_at' => null]);
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.me.account.status'), ['status' => 'active'])->assertForbidden();
        $this->assertSame(UserStatus::Pending, $user->fresh()->status);
    }
}
