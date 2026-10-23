<?php

namespace App\Actions\Notifications;

use App\Jobs\SendPushNotification;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Models\UserNotification;

/**
 * Creates an in-app notification, unless the user switched that kind off,
 * and pushes it to the browsers the user allowed notifications in.
 *
 * Every kind is on unless the user turned it off (the old app only notified
 * users who had switched a kind on, so new users heard nothing). Ratings can
 * be limited to certain star counts.
 */
class NotifyUser
{
    /**
     * Kinds of notification a user can switch off, with the label shown in settings.
     */
    public const array EVENTS = [
        'service_tip_self' => 'I receive a tip',
        'service_tip_group' => 'My team or spot receives a tip',
        'service_rating' => 'I receive a rating',
        'service_review' => 'I receive a written review',
        'join_request' => 'Business join requests and invitations',
        'accept_join_request' => 'A join request is accepted',
        'decline_join_request' => 'A join request is declined',
        'reject_agreement' => 'An invitation agreement is rejected',
        'payment_processing' => 'A payout is being processed',
        'payment_transfer' => 'A payout is transferred to my bank',
    ];

    /**
     * @param  array{title: string, message: string, link?: ?string, icon?: ?string, business_id?: ?int, audience?: string}  $notification
     */
    public function handle(int $userId, string $event, array $notification, ?int $stars = null): ?UserNotification
    {
        if (! $this->wants($userId, $event, $stars)) {
            return null;
        }

        $created = UserNotification::create([
            'user_id' => $userId,
            'business_id' => $notification['business_id'] ?? null,
            'audience' => $notification['audience'] ?? 'user',
            'type' => $event,
            'title' => $notification['title'],
            'message' => $notification['message'],
            'link' => $notification['link'] ?? null,
            'icon' => $notification['icon'] ?? null,
        ]);

        // Also to the user's browsers that allowed push notifications.
        if (PushSubscription::query()->where('user_id', $userId)->exists()) {
            SendPushNotification::dispatch($userId, $created->title, $created->message, $created->link ?? '/dashboard/notifications');
        }

        return $created;
    }

    public function wants(int $userId, string $event, ?int $stars = null): bool
    {
        $preference = NotificationPreference::query()->where('user_id', $userId)->where('event', $event)->first();

        if ($preference === null) {
            return true;
        }
        if (! $preference->is_enabled) {
            return false;
        }

        // For ratings, "channel" may hold the star counts the user wants to hear about, e.g. "1,2,3".
        if ($stars !== null && $event === 'service_rating' && filled($preference->channel)) {
            return in_array((string) $stars, explode(',', (string) $preference->channel), true);
        }

        return true;
    }
}
