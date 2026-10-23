<?php

namespace Tests\Feature;

use App\Actions\Notifications\NotifyUser;
use App\Jobs\SendPushNotification;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPush\WebPush;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use LazilyRefreshDatabase;

    private \OpenSSLAsymmetricKey $browserKey;

    private string $browserPublic;

    protected function setUp(): void
    {
        parent::setUp();
        $server = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($server, $pem);
        $details = openssl_pkey_get_details($server);
        config(['services.webpush' => [
            'public_key' => WebPush::base64UrlEncode("\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)),
            'private_key' => base64_encode($pem),
            'subject' => 'mailto:test@tiqs.test',
        ]]);

        $this->browserKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $browser = openssl_pkey_get_details($this->browserKey);
        $this->browserPublic = "\x04".str_pad($browser['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($browser['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    /**
     * Decrypts like a browser does (RFC 8291), so the test checks the format end to end.
     */
    private function decrypt(string $body, string $authSecret): string
    {
        $salt = substr($body, 0, 16);
        $keyLength = ord($body[20]);
        $serverPublic = substr($body, 21, $keyLength);
        $encrypted = substr($body, 21 + $keyLength);

        $shared = openssl_pkey_derive(WebPush::publicKeyFromRaw($serverPublic), $this->browserKey, 32);
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$this->browserPublic.$serverPublic, $authSecret);
        $key = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $plain = openssl_decrypt(substr($encrypted, 0, -16), 'aes-128-gcm', $key, OPENSSL_RAW_DATA, $nonce, substr($encrypted, -16));

        $this->assertNotFalse($plain, 'The browser could not decrypt the message.');
        $this->assertSame("\x02", substr($plain, -1));

        return substr($plain, 0, -1);
    }

    public function test_messages_are_encrypted_for_the_browser_and_signed(): void
    {
        $auth = random_bytes(16);
        $subscription = PushSubscription::factory()->create([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'public_key' => WebPush::base64UrlEncode($this->browserPublic),
            'auth_token' => WebPush::base64UrlEncode($auth),
        ]);
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);

        $this->assertTrue(app(WebPush::class)->send($subscription, ['title' => 'Tip received', 'body' => 'You received € 5,00']));

        Http::assertSent(function (Request $request) use ($auth): bool {
            $this->assertSame('{"title":"Tip received","body":"You received € 5,00"}', $this->decrypt($request->body(), $auth));
            $this->assertSame('aes128gcm', $request->header('Content-Encoding')[0]);
            [$jwt] = explode(', ', substr($request->header('Authorization')[0], strlen('vapid t=')));
            $claims = json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);
            $this->assertSame('https://fcm.googleapis.com', $claims['aud']);
            $this->assertSame(1, openssl_verify(explode('.', $jwt)[0].'.'.explode('.', $jwt)[1], $this->rawToDer(base64_decode(strtr(explode('.', $jwt)[2], '-_', '+/'))), WebPush::publicKeyFromRaw(base64_decode(strtr(config('services.webpush.public_key'), '-_', '+/'))), OPENSSL_ALGO_SHA256));

            return true;
        });
    }

    public function test_browsers_that_left_are_forgotten(): void
    {
        $subscription = PushSubscription::factory()->create(['public_key' => WebPush::base64UrlEncode($this->browserPublic), 'auth_token' => WebPush::base64UrlEncode(random_bytes(16))]);
        Http::fake(['*' => Http::response('', 410)]);

        (new SendPushNotification($subscription->user_id, 'Hi', 'There', '/dashboard'))->handle(app(WebPush::class));

        $this->assertModelMissing($subscription);
    }

    public function test_notifications_are_pushed_only_to_users_with_a_browser_subscribed(): void
    {
        Queue::fake();
        $subscribed = PushSubscription::factory()->create()->user;
        $other = User::factory()->create();

        app(NotifyUser::class)->handle($subscribed->id, 'service_tip_self', ['title' => 'Tip received', 'message' => 'You received a tip of € 5.00.']);
        app(NotifyUser::class)->handle($other->id, 'service_tip_self', ['title' => 'Tip received', 'message' => 'You received a tip of € 5.00.']);

        Queue::assertPushed(SendPushNotification::class, 1);
        Queue::assertPushed(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->userId === $subscribed->id && $job->url === '/dashboard/notifications');
    }

    public function test_users_subscribe_and_unsubscribe_a_browser(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $subscription = ['endpoint' => 'https://web.push.apple.com/abc', 'keys' => ['p256dh' => WebPush::base64UrlEncode($this->browserPublic), 'auth' => WebPush::base64UrlEncode(random_bytes(16))]];

        $this->getJson(route('api.v1.me.push.show'))->assertOk()->assertJsonPath('data.available', true)->assertJsonPath('data.devices', 0);
        $this->postJson(route('api.v1.me.push.store'), $subscription)->assertCreated()->assertJsonPath('data.devices', 1);
        $this->postJson(route('api.v1.me.push.store'), $subscription)->assertCreated()->assertJsonPath('data.devices', 1);
        $this->deleteJson(route('api.v1.me.push.destroy'), ['endpoint' => 'https://web.push.apple.com/abc'])->assertNoContent();
        $this->assertSame(0, PushSubscription::count());
    }

    /**
     * JWT signatures are r and s side by side; openssl verifies DER.
     */
    private function rawToDer(string $raw): string
    {
        $integer = function (string $part): string {
            $part = ltrim($part, "\0");
            if (ord($part[0]) > 0x7F) {
                $part = "\0".$part;
            }

            return "\x02".chr(strlen($part)).$part;
        };
        $sequence = $integer(substr($raw, 0, 32)).$integer(substr($raw, 32));

        return "\x30".chr(strlen($sequence)).$sequence;
    }
}
