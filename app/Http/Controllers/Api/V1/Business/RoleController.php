<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Requests\Business\SaveRoleRequest;
use App\Http\Resources\BusinessRoleResource;
use App\Models\BusinessRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Roles in the business (waiter, bar, kitchen…). Agreements share tips per role.
 */
class RoleController extends BusinessController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return BusinessRoleResource::collection(
            $this->business($request)->roles()->withCount('employees')->orderBy('name')->get(),
        );
    }

    public function store(SaveRoleRequest $request): BusinessRoleResource
    {
        $role = $this->business($request)->roles()->create([
            'name' => $request->validated('name'),
            'receives_tips' => $request->validated('receives_tips'),
            'level' => $request->validated('level') ?? 3,
            'parent_id' => $request->validated('parent_id'),
            'is_active' => true,
        ]);

        return new BusinessRoleResource($role);
    }

    public function update(SaveRoleRequest $request, BusinessRole $role): BusinessRoleResource
    {
        $this->ensureOwned($request, $role);

        $role->update([
            'name' => $request->validated('name'),
            'receives_tips' => $request->validated('receives_tips'),
            'level' => $request->validated('level') ?? $role->level,
            'parent_id' => $request->has('parent_id') ? $request->validated('parent_id') : $role->parent_id,
        ]);

        return new BusinessRoleResource($role);
    }

    /**
     * Team members with this role keep working without a role until given a new one.
     */
    public function destroy(Request $request, BusinessRole $role): Response
    {
        $this->ensureOwned($request, $role);
        $role->delete();

        return response()->noContent();
    }
}
