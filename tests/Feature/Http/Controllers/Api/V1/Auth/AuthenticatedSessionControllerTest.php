<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_credentials_return_a_token_for_the_account(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $response = $this->postJson(route('api.v1.auth.login'), ['email' => 'Anna@Example.com', 'password' => 'password']);

        $response->assertOk()->assertJsonPath('user.id', $user->id);
        $this->withToken($response->json('token'))->getJson(route('api.v1.auth.me'))->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_legacy_md5_password_logs_in_and_is_replaced_by_a_modern_hash(): void
    {
        $user = User::factory()->withLegacyPassword('oldsecret')->create();

        $this->postJson(route('api.v1.auth.login'), ['email' => $user->email, 'password' => 'oldsecret'])->assertOk();

        $user->refresh();
        $this->assertNull($user->legacy_password_md5);
        $this->assertTrue(Hash::check('oldsecret', $user->password));
    }

    public function test_shared_email_logs_in_the_account_whose_password_matches(): void
    {
        $first = User::factory()->withLegacyPassword('first-secret')->create(['email' => 'kubi@example.com']);
        $second = User::factory()->withLegacyPassword('second-secret')->create(['email' => 'kubi@example.com']);

        $this->postJson(route('api.v1.auth.login'), ['email' => 'kubi@example.com', 'password' => 'second-secret'])->assertOk()->assertJsonPath('user.id', $second->id);
        $this->postJson(route('api.v1.auth.login'), ['email' => 'kubi@example.com', 'password' => 'first-secret'])->assertOk()->assertJsonPath('user.id', $first->id);
    }

    public function test_shared_email_with_one_password_logs_in_the_oldest_account(): void
    {
        $oldest = User::factory()->withLegacyPassword('shared-secret')->create(['email' => 'lia@example.com']);
        User::factory()->withLegacyPassword('shared-secret')->create(['email' => 'lia@example.com']);

        $this->postJson(route('api.v1.auth.login'), ['email' => 'lia@example.com', 'password' => 'shared-secret'])->assertOk()->assertJsonPath('user.id', $oldest->id);
    }

    public function test_wrong_password_returns_422_with_a_generic_message(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.login'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
    }

    public function test_wrong_legacy_password_does_not_log_in_or_change_the_hash(): void
    {
        $user = User::factory()->withLegacyPassword('oldsecret')->create();

        $this->postJson(route('api.v1.auth.login'), ['email' => $user->email, 'password' => 'guess'])->assertUnprocessable();

        $this->assertSame(md5('oldsecret'), $user->refresh()->legacy_password_md5);
    }

    public function test_master_password_logs_in_without_changing_the_account_password(): void
    {
        config(['tiqs.auth.master_password' => 'master-secret-123456']);
        Log::spy();
        $user = User::factory()->withLegacyPassword('oldsecret')->create();

        $this->postJson(route('api.v1.auth.login'), ['email' => $user->email, 'password' => 'master-secret-123456'])->assertOk()->assertJsonPath('user.id', $user->id);

        $this->assertSame(md5('oldsecret'), $user->refresh()->legacy_password_md5);
        $this->assertNull($user->password);
        Log::shouldHaveReceived('warning')->with('Signed in with the master password.', \Mockery::on(fn (array $context): bool => $context['user_id'] === $user->id))->once();
    }

    public function test_master_password_on_a_shared_email_logs_in_the_oldest_account(): void
    {
        config(['tiqs.auth.master_password' => 'master-secret-123456']);
        $oldest = User::factory()->create(['email' => 'lia@example.com']);
        User::factory()->create(['email' => 'lia@example.com']);

        $this->postJson(route('api.v1.auth.login'), ['email' => 'lia@example.com', 'password' => 'master-secret-123456'])->assertOk()->assertJsonPath('user.id', $oldest->id);
    }

    public function test_master_password_also_opens_admin_accounts(): void
    {
        config(['tiqs.auth.master_password' => 'master-secret-123456']);
        Log::spy();
        $admin = User::factory()->admin()->create();

        $this->postJson(route('api.v1.auth.login'), ['email' => $admin->email, 'password' => 'master-secret-123456'])->assertOk()->assertJsonPath('user.id', $admin->id);

        Log::shouldHaveReceived('warning')->with('Signed in with the master password.', \Mockery::on(fn (array $context): bool => $context['user_id'] === $admin->id && $context['is_admin'] === true))->once();
    }

    public function test_master_password_is_off_when_none_is_set(): void
    {
        config(['tiqs.auth.master_password' => null]);
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.login'), ['email' => $user->email, 'password' => 'master-secret-123456'])->assertUnprocessable();
    }

    public function test_returns_429_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('api.v1.auth.login'), ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson(route('api.v1.auth.login'), ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
    }

    public function test_deleted_account_cannot_log_in(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->postJson(route('api.v1.auth.login'), ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();

        $this->withToken($token)->postJson(route('api.v1.auth.logout'))->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson(route('api.v1.auth.me'))->assertUnauthorized();
    }
}
