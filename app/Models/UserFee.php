<?php

namespace App\Models;

use Database\Factories\UserFeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-set fee override for one user.
 */
#[Fillable(['user_id', 'type', 'applies_to', 'fixed_fee', 'percent_fee', 'is_active'])]
class UserFee extends Model
{
    /** @use HasFactory<UserFeeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fixed_fee' => 'decimal:2',
            'percent_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
