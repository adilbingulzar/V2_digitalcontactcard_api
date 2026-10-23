<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_deletes_the_account_and_signs_out_everywhere(): void
    {
        $owner = User::factory()->business()->create(['slug' => 'anna']);
        $business = Business::factory()->for($owner, 'owner')->create();
        $employment = Employee::factory()->for($owner)->create();
        $token = $owner->createToken('this');

        $this->withToken($token->plainTextToken)
            ->deleteJson(route('api.v1.me.account.destroy'), ['password' => 'password'])
            ->assertNoContent();

        $this->assertSoftDeleted($owner);
        $this->assertFalse($business->fresh()->is_active);
        $this->assertModelMissing($employment);
        $this->assertModelMissing($token->accessToken);
        $this->getJson(route('api.v1.public.profiles.show', 'anna'))->assertNotFound();
    }

    public function test_requires_the_password(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('this')->plainTextToken)
            ->deleteJson(route('api.v1.me.account.destroy'), ['password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertNotSoftDeleted($user);
    }

    public function test_waits_until_open_payouts_are_paid(): void
    {
        $user = User::factory()->create();
        Withdrawal::factory()->for($user)->create();

        $this->withToken($user->createToken('this')->plainTextToken)
            ->deleteJson(route('api.v1.me.account.destroy'), ['password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertNotSoftDeleted($user);
    }
}
