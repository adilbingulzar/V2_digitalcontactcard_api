<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SsoSessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string SECRET = 'test-shared-secret';

    public function test_confirmed_account_receives_a_token(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.sso'), ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);
    }

    public function test_unconfirmed_account_is_refused_with_403(): void
    {
        $user = User::factory()->unverified()->create();

        $this->postJson(route('api.v1.auth.sso'), ['email' => $user->email, 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Please confirm your email address before signing in.');
    }

    public function test_wrong_password_returns_422(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.sso'), ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
    }

    public function test_returns_404_when_sso_is_disabled(): void
    {
        config(['tiqs.sso.enabled' => false]);
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.sso'), ['email' => $user->email, 'password' => 'password'])->assertNotFound();
    }

    public function test_valid_signature_is_accepted_when_signatures_are_required(): void
    {
        $this->requireSignatures();
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.sso'), $this->signedPayload($user->email))->assertOk();
    }

    public function test_unsigned_post_is_rejected_when_signatures_are_required(): void
    {
        $this->requireSignatures();
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.sso'), ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sso_ts', 'sso_nonce', 'sso_sig']);
    }

    public function test_signature_for_another_email_returns_403(): void
    {
        $this->requireSignatures();
        $user = User::factory()->create();
        $payload = $this->signedPayload('someone-else@example.com');
        $payload['email'] = $user->email;

        $this->postJson(route('api.v1.auth.sso'), $payload)->assertForbidden();
    }

    public function test_replayed_signed_post_returns_403(): void
    {
        $this->requireSignatures();
        $user = User::factory()->create();
        $payload = $this->signedPayload($user->email);
        $this->postJson(route('api.v1.auth.sso'), $payload)->assertOk();

        $this->postJson(route('api.v1.auth.sso'), $payload)->assertForbidden();
    }

    public function test_expired_signature_returns_403(): void
    {
        $this->requireSignatures();
        $user = User::factory()->create();
        $payload = $this->signedPayload($user->email);
        $this->travel(config('tiqs.sso.max_age_seconds') + 1)->seconds();

        $this->postJson(route('api.v1.auth.sso'), $payload)->assertForbidden();
    }

    private function requireSignatures(): void
    {
        config([
            'tiqs.sso.require_signature' => true,
            'tiqs.sso.shared_secret' => self::SECRET,
        ]);
    }

    /**
     * Build the post the way sso-client/signed-relay.php on itsmycontact.info does.
     *
     * @return array<string, string|int>
     */
    private function signedPayload(string $email): array
    {
        $timestamp = now()->timestamp;
        $nonce = bin2hex(random_bytes(16));
        $payload = config('tiqs.sso.issuer').'|'.config('tiqs.sso.audience').'|'.$email.'|'.$timestamp.'|'.$nonce;

        return [
            'email' => $email,
            'password' => 'password',
            'sso_ts' => $timestamp,
            'sso_nonce' => $nonce,
            'sso_sig' => hash_hmac('sha256', $payload, self::SECRET),
        ];
    }
}
