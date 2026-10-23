<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One checkout at Pay.nl. Written only by server-side payment code, so nothing
 * is mass-assignable; the status only changes after asking Pay.nl.
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'pay_method_id' => 'integer',
            'pay_status_code' => 'integer',
            'tip_amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'method_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'status_checked_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<Spot, $this>
     */
    public function spot(): BelongsTo
    {
        return $this->belongsTo(Spot::class);
    }

    /**
     * @return HasMany<Tip, $this>
     */
    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class);
    }

    /**
     * @return HasMany<FeeCollection, $this>
     */
    public function feeCollections(): HasMany
    {
        return $this->hasMany(FeeCollection::class);
    }
}
