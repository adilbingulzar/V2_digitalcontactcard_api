<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use App\Services\WebPush\WebPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Sends one in-app notification as a push message to every browser of the
 * user. Browsers that no longer accept messages are forgotten.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public string $url,
    ) {}

    public function handle(WebPush $webPush): void
    {
        if (! $webPush->isConfigured()) {
            return;
        }

        $payload = ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];

        PushSubscription::query()->where('user_id', $this->userId)->each(function (PushSubscription $subscription) use ($webPush, $payload): void {
            try {
                if ($webPush->send($subscription, $payload)) {
                    $subscription->forceFill(['last_used_at' => now()])->save();
                } else {
                    $subscription->delete();
                }
            } catch (RuntimeException $exception) {
                report($exception);
            }
        });
    }
}
