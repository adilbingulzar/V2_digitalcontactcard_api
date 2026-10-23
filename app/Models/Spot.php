<?php

namespace App\Models;

use Database\Factories\SpotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place inside a business (table, bar, counter) with its own QR code.
 */
#[Fillable(['name', 'slug', 'is_multi', 'is_active'])]
class Spot extends Model
{
    /** @use HasFactory<SpotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_multi' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return HasMany<SpotEmployee, $this>
     */
    public function spotEmployees(): HasMany
    {
        return $this->hasMany(SpotEmployee::class);
    }

    /**
     * @return HasMany<Tip, $this>
     */
    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class);
    }
}
