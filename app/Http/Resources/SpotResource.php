<?php

namespace App\Http\Resources;

use App\Models\Spot;
use App\Models\SpotEmployee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A spot as its business owner manages it.
 *
 * @mixin Spot
 */
class SpotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            // Printed spot QR codes open this address (old URL format).
            'url' => config('tiqs.frontend_url').'/paytips/spot/'.$this->business_id.'/'.$this->slug,
            'staff' => $this->whenLoaded('spotEmployees', fn () => $this->spotEmployees->map(fn (SpotEmployee $member): array => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'role' => $member->role === null ? null : ['id' => $member->role->id, 'name' => $member->role->name],
                'is_live' => $member->is_live,
            ])->values()),
        ];
    }
}
