<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Notifications\NotifyUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * Every kind of notification with whether the user wants it. Kinds the
     * user never changed are on. For ratings, an empty star list means all.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->preferences($request->user())]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();

        foreach ($request->validated('preferences') as $preference) {
            $stars = collect($preference['stars'] ?? [])->map(fn ($star): int => (int) $star)->sort()->values();
            $isRating = $preference['event'] === 'service_rating';

            $saved = NotificationPreference::query()->whereBelongsTo($user)->where('event', $preference['event'])->first();

            ($saved ?? new NotificationPreference)
                ->forceFill([
                    'user_id' => $user->id,
                    'event' => $preference['event'],
                    'is_enabled' => $preference['enabled'],
                    'channel' => $isRating && $stars->isNotEmpty() && $stars->count() < 5 ? $stars->implode(',') : null,
                ])
                ->save();
        }

        return response()->json(['data' => $this->preferences($user)]);
    }

    /**
     * @return list<array{event: string, label: string, enabled: bool, stars: list<int>}>
     */
    private function preferences(User $user): array
    {
        $saved = NotificationPreference::query()->whereBelongsTo($user)->get()->keyBy('event');

        return collect(NotifyUser::EVENTS)->map(function (string $label, string $event) use ($saved): array {
            $preference = $saved->get($event);
            $stars = $event === 'service_rating' && filled($preference?->channel)
                ? array_map('intval', explode(',', (string) $preference->channel))
                : [];

            return [
                'event' => $event,
                'label' => $label,
                'enabled' => $preference === null || $preference->is_enabled,
                'stars' => $stars,
            ];
        })->values()->all();
    }
}
