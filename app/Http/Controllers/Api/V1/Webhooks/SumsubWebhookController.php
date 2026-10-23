<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Sumsub\SumsubClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sumsub tells us when a check is finished. Only messages signed with the
 * webhook secret are accepted.
 */
class SumsubWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.sumsub.webhook_secret');
        $algorithm = match (strtoupper((string) $request->header('X-Payload-Digest-Alg', 'HMAC_SHA256_HEX'))) {
            'HMAC_SHA1_HEX' => 'sha1',
            'HMAC_SHA512_HEX' => 'sha512',
            default => 'sha256',
        };
        $expected = hash_hmac($algorithm, $request->getContent(), $secret);

        abort_if($secret === '' || ! hash_equals($expected, (string) $request->header('X-Payload-Digest')), 401);

        $externalId = (string) $request->input('externalUserId');
        $userId = (int) substr($externalId, (int) strrpos($externalId, '_') + 1);
        $user = User::query()->find($userId);

        if ($user !== null && hash_equals($user->slug.'_'.$user->id, $externalId)) {
            $status = SumsubClient::statusFrom($request->input('reviewStatus'), $request->input('reviewResult.reviewAnswer'));
            if ($status !== 'none') {
                $user->forceFill(['identity_status' => $status, 'identity_checked_at' => now()])->save();
            }
        }

        return response()->json(['ok' => true]);
    }
}
