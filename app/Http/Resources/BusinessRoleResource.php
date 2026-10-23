<?php

namespace App\Http\Resources;

use App\Models\BusinessRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessRole
 */
class BusinessRoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'receives_tips' => $this->receives_tips,
            'level' => $this->level,
            'parent_id' => $this->parent_id,
            'members' => $this->whenCounted('employees'),
        ];
    }
}
