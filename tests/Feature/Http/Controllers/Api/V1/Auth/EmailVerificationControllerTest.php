<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Models\Referral;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_correct_code_confirms_email_activates_account_and_confirms_referral(): void
    {
        $user = User::factory()->unverified()->create();
        VerificationCode::factory()->for($user)->create();
        $referral = Referral::factory()->create(['referred_user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.auth.verification.verify'), ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue($referral->refresh()->is_confirmed);
        $this->assertSame(0, VerificationCode::count());
    }

    public function test_wrong_code_returns_422_and_counts_the_attempt(): void
    {
        $user = User::factory()->unverified()->create();
        $code = VerificationCode::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.auth.verification.verify'), ['code' => '654321'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'This code is not correct.']);

        $this->assertSame(1, $code->refresh()->attempts);
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        VerificationCode::factory()->for($user)->create(['expires_at' => now()->subMinute()]);
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.auth.verification.verify'), ['code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'This code has expired. Request a new one.']);
    }

    public function test_correct_code_is_rejected_after_the_maximum_attempts(): void
    {
        $user = User::factory()->unverified()->create();
        VerificationCode::factory()->for($user)->create(['attempts' => VerificationCode::MAX_ATTEMPTS]);
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.auth.verification.verify'), ['code' => '123456'])->assertUnprocessable();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson(route('api.v1.auth.verification.verify'), ['code' => '123456'])->assertUnauthorized();
    }
}
