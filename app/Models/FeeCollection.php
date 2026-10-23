<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\FeeCollectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform fee and tax taken from a tip. Written only by server-side payment code.
 */
class FeeCollection extends Model
{
    /** @use HasFactory<FeeCollectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'tip_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Tip, $this>
     */
    public function tip(): BelongsTo
    {
        return $this->belongsTo(Tip::class);
    }

    /**
     * @return BelongsTo<Withdrawal, $this>
     */
    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }
}
