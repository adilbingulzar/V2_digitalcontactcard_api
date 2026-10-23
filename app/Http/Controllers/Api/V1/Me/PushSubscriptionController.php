<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPush\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Browsers that receive push notifications for the signed-in user.
 */
class PushSubscriptionController extends Controller
{
    /**
     * The key browsers need to subscribe, and how many browsers already do.
     */
    public function show(Request $request, WebPush $webPush): JsonResponse
    {
        return response()->json(['data' => [
            'available' => $webPush->isConfigured(),
            'public_key' => $webPush->isConfigured() ? config('services.webpush.public_key') : null,
            'devices' => PushSubscription::query()->whereBelongsTo($request->user())->count(),
        ]]);
    }

    public function store(Request $request, WebPush $webPush): JsonResponse
    {
        abort_unless($webPush->isConfigured(), 503, 'Push notifications are not available right now.');
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{80,100}$/'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,40}$/'],
        ]);

        $hash = hash('sha256', $validated['endpoint']);
        $subscription = PushSubscription::query()->where('endpoint_hash', $hash)->first() ?? new PushSubscription;
        $subscription->forceFill([
            'user_id' => $request->user()->id,
            'endpoint' => $validated['endpoint'],
            'endpoint_hash' => $hash,
            'public_key' => $validated['keys']['p256dh'],
            'auth_token' => $validated['keys']['auth'],
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ])->save();

        return response()->json(['data' => ['devices' => PushSubscription::query()->whereBelongsTo($request->user())->count()]], 201);
    }

    public function destroy(Request $request): Response
    {
        $validated = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        PushSubscription::query()->whereBelongsTo($request->user())->where('endpoint_hash', hash('sha256', $validated['endpoint']))->delete();

        return response()->noContent();
    }
}
