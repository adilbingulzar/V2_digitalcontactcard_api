<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmploymentLiveController extends Controller
{
    /**
     * Go live (on shift) or offline at a business.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize('setLive', $employee);

        $validated = $request->validate(['is_live' => ['required', 'boolean']]);

        $employee->forceFill([
            'is_live' => $validated['is_live'],
            'last_live_at' => $validated['is_live'] ? now() : $employee->last_live_at,
        ])->save();

        return response()->json(['data' => ['id' => $employee->id, 'is_live' => $employee->is_live]]);
    }
}
