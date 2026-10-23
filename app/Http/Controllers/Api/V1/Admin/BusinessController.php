<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends AdminController
{
    /**
     * Businesses by name or code, e.g. to add imported accounts to one.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $page = Business::query()
            ->when($validated['q'] ?? null, fn (Builder $businesses, string $q) => $businesses->where(fn (Builder $match) => $match->where('name', 'like', '%'.$q.'%')->orWhere('code', 'like', '%'.$q.'%')))
            ->with('owner:id,name,email')
            ->withCount(['employees' => fn (Builder $employees) => $employees->where('status', 'accepted')])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return $this->paginated($page, fn (Business $business): array => [
            'id' => $business->id,
            'name' => $business->name,
            'code' => $business->code,
            'owner' => $business->owner?->only(['id', 'name', 'email']),
            'team_size' => (int) $business->employees_count,
            'is_active' => $business->is_active,
        ]);
    }
}
