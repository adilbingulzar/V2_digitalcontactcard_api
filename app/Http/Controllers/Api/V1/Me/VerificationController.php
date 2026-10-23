<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Sumsub\SumsubClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Identity verification (the old "Verification" page): the user's status, and
 * a token for the Sumsub web SDK that runs the check in the browser.
 */
class VerificationController extends Controller
{
    public function show(Request $request, SumsubClient $sumsub): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'available' => $sumsub->isConfigured(),
            'status' => $user->identity_status ?? 'none',
            'checked_at' => $user->identity_checked_at?->toIso8601String(),
        ]]);
    }

    public function token(Request $request, SumsubClient $sumsub): JsonResponse
    {
        abort_unless($sumsub->isConfigured(), 503, 'Identity verification is not available right now.');
        $user = $request->user();

        try {
            $token = $sumsub->accessToken(self::externalId($user));
        } catch (RuntimeException $exception) {
            report($exception);
            abort(502, 'Identity verification could not be started. Please try again later.');
        }

        if (($user->identity_status ?? 'none') === 'none') {
            $user->forceFill(['identity_status' => 'pending'])->save();
        }

        return response()->json(['data' => ['token' => $token]]);
    }

    /**
     * The id Sumsub knows the user by; the same format as the old app, so earlier checks still count.
     */
    public static function externalId(User $user): string
    {
        return $user->slug.'_'.$user->id;
    }
}
