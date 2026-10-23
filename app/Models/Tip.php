<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\TipType;
use Database\Factories\TipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tip, rating or review. A tip given at a spot has `spot_id` set.
 *
 * Payment fields (`payment_id`, `payment_status`, `paid_at`, `pay_order_id`, `withdrawal_id`)
 * are deliberately not fillable: they may only change after the payment is
 * confirmed with Pay.nl, never from request input.
 */
#[Fillable([
    'type', 'recipient_id', 'business_id', 'spot_id', 'sender_id', 'amount', 'currency',
    'rating', 'review', 'reviewer_name', 'reviewer_email', 'ip_address',
])]
class Tip extends Model
{
    /** @use HasFactory<TipFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TipType::class,
            'payment_status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'rating' => 'integer',
            'is_review_pinned' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Paid tips that have not been requested for payout yet: the recipient's balance.
     */
    #[Scope]
    protected function availableForPayout(Builder $query): void
    {
        $query->where('payment_status', PaymentStatus::Paid)->whereNull('withdrawal_id');
    }

    #[Scope]
    protected function paid(Builder $query): void
    {
        $query->where('payment_status', PaymentStatus::Paid);
    }

    /**
     * Ratings and reviews that count: those left on their own, and those given
     * with a tip that was actually paid. A rating from an abandoned checkout does not count.
     */
    #[Scope]
    protected function countsAsFeedback(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('type', TipType::Rating)
            ->orWhere('payment_status', PaymentStatus::Paid));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Spot, $this>
     */
    public function spot(): BelongsTo
    {
        return $this->belongsTo(Spot::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Withdrawal, $this>
     */
    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }
}
