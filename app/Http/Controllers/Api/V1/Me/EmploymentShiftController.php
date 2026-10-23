<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Business\ReplaceShifts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\SaveOwnShiftsRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The signed-in user's own weekly working hours at a business they work at.
 * The business can see and change the same hours on its team page.
 */
class EmploymentShiftController extends Controller
{
    public function show(Employee $employee, ReplaceShifts $shifts): JsonResponse
    {
        Gate::authorize('setOwnShifts', $employee);

        return response()->json(['data' => $shifts->current('shift', $employee->user_id, $employee->business_id, null)]);
    }

    public function update(SaveOwnShiftsRequest $request, Employee $employee, ReplaceShifts $shifts): JsonResponse
    {
        Gate::authorize('setOwnShifts', $employee);

        $shifts->handle('shift', $employee->user_id, $employee->business_id, null, $request->days());

        return response()->json(['data' => $shifts->current('shift', $employee->user_id, $employee->business_id, null)]);
    }
}
