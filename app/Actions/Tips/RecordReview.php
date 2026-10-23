<?php

namespace App\Actions\Tips;

use App\Actions\Notifications\NotifyUser;
use App\Enums\PaymentStatus;
use App\Enums\TipType;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Saves a rating and/or review left on a public page without paying a tip,
 * and notifies the user (unless they switched these notifications off).
 */
class RecordReview
{
    public function __construct(private NotifyUser $notifyUser) {}

    /**
     * @param  array{rating: int, review?: ?string, name?: ?string, email?: ?string}  $input
     */
    public function handle(User $recipient, array $input, ?string $ipAddress, ?int $businessId = null, ?int $spotId = null): Tip
    {
        $tip = new Tip([
            'type' => TipType::Rating,
            'recipient_id' => $recipient->id,
            'business_id' => $businessId,
            'spot_id' => $spotId,
            'amount' => 0,
            'currency' => config('tiqs.tips.currency'),
            'rating' => $input['rating'],
            'review' => $input['review'] ?? null,
            'reviewer_name' => $input['name'] ?? null,
            'reviewer_email' => $input['email'] ?? null,
            'ip_address' => $ipAddress,
        ]);
        $tip->forceFill(['payment_status' => PaymentStatus::Pending, 'token' => Str::random(40)])->save();

        $this->notifyUser->handle($recipient->id, 'service_rating', [
            'title' => 'Rating Alert',
            'message' => 'You received a '.$input['rating'].' star rating.',
            'icon' => 'fa-face-grin-stars',
            'business_id' => $businessId,
        ], (int) $input['rating']);

        if (! empty($input['review'])) {
            $this->notifyUser->handle($recipient->id, 'service_review', [
                'title' => 'Review Alert',
                'message' => 'You received a new review for your service.',
                'icon' => 'fa-face-grin-stars',
                'business_id' => $businessId,
            ]);
        }

        return $tip;
    }
}
