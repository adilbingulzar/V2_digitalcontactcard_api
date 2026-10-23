<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user's own account, as returned to the frontend.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'slug' => $this->slug,
            'role' => $this->role,
            'account_type' => $this->account_type,
            'status' => $this->status,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'country' => $this->country,
            'birth_date' => $this->birth_date?->toDateString(),
            'address' => $this->address,
            'bio' => $this->bio,
            'avatar' => $this->avatarUrl(),
            'accepts_individual_tips' => $this->accepts_individual_tips,
            'business' => $this->whenLoaded('business', fn (): ?array => $this->business === null ? null : [
                'id' => $this->business->id,
                'name' => $this->business->name,
                'code' => $this->business->code,
                'email' => $this->business->email,
                'address' => $this->business->address,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
