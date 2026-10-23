<?php

namespace App\Http\Resources;

use App\Models\Agreement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agreement
 */
class AgreementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'is_for_spots' => $this->is_for_spots,
            'is_default' => $this->business->default_agreement_id === $this->id,
            'role_shares' => collect($this->role_shares)
                ->map(fn (float|int|string $percent, string|int $roleId): array => ['role_id' => (int) $roleId, 'percent' => (float) $percent])
                ->values(),
            'employees' => $this->whenCounted('employees'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
