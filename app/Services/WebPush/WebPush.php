<?php

namespace App\Services\WebPush;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Sends Web Push messages to browsers: the message is encrypted for the
 * browser (RFC 8291, "aes128gcm") and the request is signed with the site's
 * VAPID key (RFC 8292). Built on PHP's openssl, without extra packages.
 */
class WebPush
{
    /**
     * DER prefix of an uncompressed P-256 public key (SubjectPublicKeyInfo).
     */
    private const string P256_PUBLIC_KEY_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /**
     * @param  array<string, mixed>  $payload  JSON for the service worker: title, body, url
     * @return bool false when the browser no longer accepts messages (the subscription should be removed)
     *
     * @throws RuntimeException on other errors
     */
    public function send(PushSubscription $subscription, array $payload): bool
    {
        $body = $this->encrypt((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $subscription->public_key, $subscription->auth_token);
        $origin = (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $subscription->endpoint);

        $response = Http::timeout(10)
            ->withHeaders([
                'Authorization' => 'vapid t='.$this->vapidToken($origin).', k='.config('services.webpush.public_key'),
                'Content-Encoding' => 'aes128gcm',
                'TTL' => '86400',
                'Urgency' => 'normal',
            ])
            ->withBody($body, 'application/octet-stream')
            ->post($subscription->endpoint);

        if (in_array($response->status(), [404, 410], true)) {
            return false;
        }
        if ($response->failed()) {
            throw new RuntimeException('The push service answered HTTP '.$response->status().'.');
        }

        return true;
    }

    /**
     * Encrypts a message for one browser, as one aes128gcm record.
     */
    public function encrypt(string $plaintext, string $browserPublicKey, string $browserAuth, ?OpenSSLAsymmetricKey $serverKey = null, ?string $salt = null): string
    {
        $uaPublic = self::base64UrlDecode($browserPublicKey);
        $authSecret = self::base64UrlDecode($browserAuth);
        if (strlen($uaPublic) !== 65 || strlen($authSecret) < 16) {
            throw new RuntimeException('The browser keys are not valid.');
        }

        $serverKey ??= openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = openssl_pkey_get_details($serverKey);
        $asPublic = "\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $sharedSecret = openssl_pkey_derive(self::publicKeyFromRaw($uaPublic), $serverKey, 32);
        if ($sharedSecret === false) {
            throw new RuntimeException('The browser key could not be used.');
        }

        $ikm = hash_hkdf('sha256', $sharedSecret, 32, "WebPush: info\0".$uaPublic.$asPublic, $authSecret);
        $salt ??= random_bytes(16);
        $contentKey = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        $tag = '';
        $ciphertext = openssl_encrypt($plaintext."\x02", 'aes-128-gcm', $contentKey, OPENSSL_RAW_DATA, $nonce, $tag);

        return $salt.pack('N', 4096).chr(65).$asPublic.$ciphertext.$tag;
    }

    /**
     * Signed JWT (ES256) that tells the push service who sends the message.
     */
    private function vapidToken(string $audience): string
    {
        $privateKey = openssl_pkey_get_private((string) base64_decode((string) config('services.webpush.private_key'), true));
        if ($privateKey === false) {
            throw new RuntimeException('WEBPUSH_PRIVATE_KEY is not a valid key.');
        }

        $unsigned = self::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
            .'.'.self::base64UrlEncode((string) json_encode(['aud' => $audience, 'exp' => time() + 12 * 3600, 'sub' => (string) config('services.webpush.subject')], JSON_UNESCAPED_SLASHES));
        openssl_sign($unsigned, $der, $privateKey, OPENSSL_ALGO_SHA256);

        return $unsigned.'.'.self::base64UrlEncode(self::derToRaw($der));
    }

    /**
     * ECDSA signatures from openssl are DER; JWT wants the two 32-byte numbers side by side.
     */
    private static function derToRaw(string $der): string
    {
        $offset = 3 + (ord($der[1]) & 0x80 ? 1 : 0);
        $rLength = ord($der[$offset]);
        $r = substr($der, $offset + 1, $rLength);
        $offset += 1 + $rLength + 1;
        $sLength = ord($der[$offset]);
        $s = substr($der, $offset + 1, $sLength);

        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT).str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    public static function publicKeyFromRaw(string $raw): OpenSSLAsymmetricKey
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(self::P256_PUBLIC_KEY_PREFIX).$raw), 64, "\n")."-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('The browser key is not a P-256 key.');
        }

        return $key;
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
