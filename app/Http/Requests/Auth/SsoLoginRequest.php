<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Cache;

/**
 * A login posted from the itsmycontact.info form. Same credential check and
 * rate limit as a normal login, plus the optional HMAC signature of that site.
 */
class SsoLoginRequest extends LoginRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $signed = (bool) config('tiqs.sso.require_signature');

        return [
            ...parent::rules(),
            'sso_ts' => [$signed ? 'required' : 'nullable', 'integer'],
            'sso_nonce' => [$signed ? 'required' : 'nullable', 'string', 'max:100'],
            'sso_sig' => [$signed ? 'required' : 'nullable', 'string', 'size:64'],
        ];
    }

    /**
     * The signature is HMAC-SHA256 over "issuer|audience|email|timestamp|nonce" with the
     * shared secret. It must be recent, and each nonce is accepted only once.
     */
    public function hasValidSignature(): bool
    {
        if (! config('tiqs.sso.require_signature')) {
            return true;
        }

        $secret = (string) config('tiqs.sso.shared_secret');
        $timestamp = $this->integer('sso_ts');
        $nonce = $this->string('sso_nonce')->toString();
        $maxAge = (int) config('tiqs.sso.max_age_seconds');

        if ($secret === '' || abs(now()->timestamp - $timestamp) > $maxAge) {
            return false;
        }

        $payload = implode('|', [
            config('tiqs.sso.issuer'),
            config('tiqs.sso.audience'),
            $this->string('email')->toString(),
            $timestamp,
            $nonce,
        ]);

        if (! hash_equals(hash_hmac('sha256', $payload, $secret), $this->string('sso_sig')->toString())) {
            return false;
        }

        // Cache::add only succeeds for a nonce that has not been seen within the time window.
        return Cache::add('sso-nonce:'.hash('sha256', $nonce), true, $maxAge * 2);
    }
}
