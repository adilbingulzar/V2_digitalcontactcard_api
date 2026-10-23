<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VerificationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_is_unavailable_until_sumsub_is_set_up(): void
    {
        config(['services.sumsub.app_token' => null]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.me.verification.show'))->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.status', 'none');
        $this->postJson(route('api.v1.me.verification.token'))->assertServiceUnavailable();
    }

    public function test_gets_a_signed_sdk_token_for_the_user(): void
    {
        config(['services.sumsub' => ['base_url' => 'https://api.sumsub.test', 'app_token' => 'app-token', 'secret_key' => 'secret', 'level_name' => 'basic-kyc-level', 'webhook_secret' => 'hook']]);
        Http::fake(['api.sumsub.test/*' => Http::response(['token' => 'sdk-token', 'userId' => 'anna_1'])]);
        $user = User::factory()->create(['slug' => 'anna']);
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.me.verification.token'))->assertOk()->assertJsonPath('data.token', 'sdk-token');

        $this->assertSame('pending', $user->fresh()->identity_status);
        Http::assertSent(function (Request $request) use ($user): bool {
            $expected = hash_hmac('sha256', $request->header('X-App-Access-Ts')[0].'POST/resources/accessTokens/sdk'.$request->body(), 'secret');

            return $request->url() === 'https://api.sumsub.test/resources/accessTokens/sdk'
                && $request['userId'] === 'anna_'.$user->id
                && $request->header('X-App-Access-Sig')[0] === $expected;
        });
    }

    public function test_the_webhook_stores_the_result_only_when_signed(): void
    {
        config(['services.sumsub.webhook_secret' => 'hook']);
        $user = User::factory()->create(['slug' => 'anna']);
        $body = json_encode(['type' => 'applicantReviewed', 'externalUserId' => 'anna_'.$user->id, 'reviewStatus' => 'completed', 'reviewResult' => ['reviewAnswer' => 'GREEN']]);

        $this->call('POST', route('api.v1.webhooks.sumsub'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYLOAD_DIGEST' => 'forged'], $body)->assertUnauthorized();
        $this->assertSame('none', $user->fresh()->identity_status);

        $this->call('POST', route('api.v1.webhooks.sumsub'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYLOAD_DIGEST' => hash_hmac('sha256', $body, 'hook'), 'HTTP_X_PAYLOAD_DIGEST_ALG' => 'HMAC_SHA256_HEX'], $body)->assertOk();
        $this->assertSame('verified', $user->fresh()->identity_status);
        $this->assertNotNull($user->fresh()->identity_checked_at);
    }
}
