<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A team member as the business owner sees them.
 *
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'slug' => $this->user->slug,
                'avatar' => $this->user->avatarUrl(),
            ],
            'role' => $this->role === null ? null : ['id' => $this->role->id, 'name' => $this->role->name],
            'agreement' => $this->agreement === null ? null : ['id' => $this->agreement->id, 'title' => $this->agreement->title],
            'status' => $this->status,
            'requested_by' => $this->requested_by,
            'reason' => $this->reason,
            'is_live' => $this->is_live,
            'last_live_at' => $this->last_live_at?->toIso8601String(),
            'since' => $this->created_at?->toIso8601String(),
        ];
    }
}
