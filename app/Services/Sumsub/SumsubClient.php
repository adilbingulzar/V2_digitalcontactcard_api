<?php

namespace App\Services\Sumsub;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Identity checks with Sumsub. Requests are signed with the app secret
 * (HMAC-SHA256 of timestamp, method, path and body), as Sumsub requires.
 */
class SumsubClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.sumsub.app_token')) && filled(config('services.sumsub.secret_key'));
    }

    /**
     * Access token for the Sumsub web SDK, valid for one hour.
     *
     * @throws RuntimeException
     */
    public function accessToken(string $externalUserId): string
    {
        $response = $this->send('POST', '/resources/accessTokens/sdk', [
            'userId' => $externalUserId,
            'levelName' => (string) config('services.sumsub.level_name'),
            'ttlInSecs' => 3600,
        ]);

        $token = $response->json('token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Sumsub did not return an access token.');
        }

        return $token;
    }

    /**
     * The review result of a user's applicant: "verified", "rejected", "pending" or "none" (never started).
     *
     * @throws RuntimeException
     */
    public function status(string $externalUserId): string
    {
        $response = $this->send('GET', '/resources/applicants/-;externalUserId='.rawurlencode($externalUserId).'/one');
        if ($response->status() === 404) {
            return 'none';
        }

        return self::statusFrom((string) $response->json('review.reviewStatus'), (string) $response->json('review.reviewResult.reviewAnswer'));
    }

    public static function statusFrom(?string $reviewStatus, ?string $reviewAnswer): string
    {
        return match (true) {
            $reviewStatus === 'completed' && $reviewAnswer === 'GREEN' => 'verified',
            $reviewStatus === 'completed' && $reviewAnswer === 'RED' => 'rejected',
            filled($reviewStatus) && $reviewStatus !== 'init' => 'pending',
            default => 'none',
        };
    }

    /**
     * @param  array<string, mixed>|null  $body
     *
     * @throws RuntimeException
     */
    private function send(string $method, string $path, ?array $body = null): Response
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Sumsub is not set up yet.');
        }

        $json = $body === null ? '' : (string) json_encode($body);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.$method.$path.$json, (string) config('services.sumsub.secret_key'));

        $request = Http::baseUrl((string) config('services.sumsub.base_url'))
            ->timeout(15)
            ->withHeaders([
                'X-App-Token' => (string) config('services.sumsub.app_token'),
                'X-App-Access-Ts' => $timestamp,
                'X-App-Access-Sig' => $signature,
                'Accept' => 'application/json',
            ]);

        $response = $body === null
            ? $request->send($method, $path)
            : $request->withBody($json, 'application/json')->send($method, $path);

        if ($response->failed() && $response->status() !== 404) {
            throw new RuntimeException('Sumsub answered HTTP '.$response->status().'.');
        }

        return $response;
    }
}
