<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A business the signed-in user works at, was invited to, or asked to join.
 *
 * @mixin Employee
 */
class EmploymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business' => ['id' => $this->business->id, 'name' => $this->business->name, 'code' => $this->business->code],
            'role' => $this->role?->name,
            'agreement' => $this->agreement === null ? null : ['title' => $this->agreement->title, 'content' => $this->agreement->content],
            'status' => $this->status,
            'requested_by' => $this->requested_by,
            'reason' => $this->reason,
            'is_live' => $this->is_live,
        ];
    }
}
