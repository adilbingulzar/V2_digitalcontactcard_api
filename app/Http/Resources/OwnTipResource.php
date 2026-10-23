<?php

namespace App\Http\Resources;

use App\Models\Tip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A tip, rating or review as its recipient sees it. The customer's email and
 * IP address are not included.
 *
 * @mixin Tip
 */
class OwnTipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_status' => $this->payment_status,
            'paid_out' => $this->withdrawal_id !== null,
            'rating' => $this->rating,
            'review' => $this->review,
            'reviewer_name' => $this->reviewer_name,
            'business' => $this->whenLoaded('business', fn (): ?string => $this->business?->name),
            'spot' => $this->whenLoaded('spot', fn (): ?string => $this->spot?->name),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
