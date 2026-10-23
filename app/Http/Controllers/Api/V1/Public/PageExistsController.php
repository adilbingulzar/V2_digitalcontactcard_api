<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Spot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Whether a public page exists, so the website can answer "404 Not Found"
 * before it starts streaming the page. Cheap, and logs no visit.
 */
class PageExistsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'type' => ['required', 'in:profile,card,spot,payment,widget'],
            'key' => ['required', 'string', 'max:200'],
            'business' => ['nullable', 'string', 'max:20'],
        ]);
        $key = $validated['key'];

        $exists = match ($validated['type']) {
            'profile' => User::query()->where('slug', $key)->exists(),
            'card' => User::query()->where('slug', $key)->whereHas('vcard', fn ($cards) => $cards->where('is_active', true))->exists(),
            'spot' => ctype_digit((string) ($validated['business'] ?? '')) && Spot::query()->where('business_id', (int) $validated['business'])->where('slug', $key)->exists(),
            'payment' => Payment::query()->where('uuid', $key)->exists(),
            'widget' => ctype_digit($key) && Business::query()->whereKey((int) $key)->where('is_active', true)->exists(),
        };

        return response()->noContent($exists ? 204 : 404);
    }
}
