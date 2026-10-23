<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Wallet\AppleWalletPass;
use App\Services\Wallet\BusinessCard;
use App\Services\Wallet\GoogleWalletPass;
use App\Services\Wallet\WalletException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Business card passes for Google Wallet and Apple Wallet. Both are made fresh
 * on every request, so they always show the card's current details.
 */
class WalletPassController extends Controller
{
    public function google(User $user, GoogleWalletPass $wallet): JsonResponse
    {
        $card = $this->card($user);

        try {
            return response()->json(['data' => ['url' => $wallet->saveUrl($card)]]);
        } catch (WalletException $exception) {
            report($exception);

            abort(503, 'Google Wallet is not available right now.');
        }
    }

    public function apple(User $user, AppleWalletPass $wallet): Response
    {
        $card = $this->card($user);

        try {
            $pass = $wallet->create($card, $user);
        } catch (WalletException $exception) {
            report($exception);

            abort(503, 'Apple Wallet is not available right now.');
        }

        return response($pass, 200, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Content-Disposition' => 'attachment; filename="'.(Str::slug($user->name) ?: 'card').'.pkpass"',
        ]);
    }

    private function card(User $user): BusinessCard
    {
        abort_unless($user->vcard?->is_active, 404);

        return BusinessCard::for($user, $user->vcard, $user->vcardTheme);
    }
}
