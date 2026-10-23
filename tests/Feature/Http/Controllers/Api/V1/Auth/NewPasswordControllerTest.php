<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class NewPasswordControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_token_sets_the_password_and_ends_existing_sign_ins(): void
    {
        $user = User::factory()->withLegacyPassword()->create();
        $user->issueApiToken();
        $token = Password::createToken($user);

        $this->postJson(route('api.v1.auth.password.store'), $this->payload($user, $token))->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('newsecret1', $user->password));
        $this->assertNull($user->legacy_password_md5);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_token_cannot_be_used_twice(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->postJson(route('api.v1.auth.password.store'), $this->payload($user, $token))->assertOk();

        $this->postJson(route('api.v1.auth.password.store'), $this->payload($user, $token, 'another2'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'This password reset token is invalid.']);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->postJson(route('api.v1.auth.password.store'), $this->payload($user, $token))->assertUnprocessable();

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    /**
     * @return array<string, string>
     */
    private function payload(User $user, string $token, string $password = 'newsecret1'): array
    {
        return [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }
}
