<?php

namespace App\Models;

use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'address', 'default_agreement_id', 'is_active'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Agreement, $this>
     */
    public function defaultAgreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'default_agreement_id');
    }

    /**
     * @return HasMany<BusinessRole, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(BusinessRole::class);
    }

    /**
     * @return HasMany<Agreement, $this>
     */
    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @return HasMany<Spot, $this>
     */
    public function spots(): HasMany
    {
        return $this->hasMany(Spot::class);
    }

    /**
     * @return HasMany<Tip, $this>
     */
    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class);
    }
}
