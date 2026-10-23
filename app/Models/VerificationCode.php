<?php

namespace App\Models;

use Database\Factories\VerificationCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Short-lived, hashed code sent to confirm an email address.
 * Written only by server-side code, so nothing is mass-assignable.
 */
#[Hidden(['code_hash'])]
class VerificationCode extends Model
{
    /** @use HasFactory<VerificationCodeFactory> */
    use HasFactory;

    public const int MAX_ATTEMPTS = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->expires_at->isFuture() && $this->attempts < self::MAX_ATTEMPTS;
    }
}
