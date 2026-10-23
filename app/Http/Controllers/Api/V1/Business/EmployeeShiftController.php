<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\Business\ReplaceShifts;
use App\Enums\EmployeeStatus;
use App\Http\Requests\Business\SaveShiftsRequest;
use App\Models\Employee;
use App\Models\SpotEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Weekly shift hours of a team member, at the business or at one of its spots.
 * During a shift the member shares in team tips without having to go live.
 */
class EmployeeShiftController extends BusinessController
{
    public function show(Request $request, Employee $employee, ReplaceShifts $shifts): JsonResponse
    {
        $this->ensureOwned($request, $employee);

        return response()->json(['data' => $shifts->current('shift', $employee->user_id, $employee->business_id, null)]);
    }

    public function update(SaveShiftsRequest $request, Employee $employee, ReplaceShifts $shifts): JsonResponse
    {
        $this->ensureOwned($request, $employee);
        abort_unless($employee->status === EmployeeStatus::Accepted, 422, 'Only team members can have shifts.');

        $shifts->handle('shift', $employee->user_id, $employee->business_id, null, $request->days());

        return response()->json(['data' => $shifts->current('shift', $employee->user_id, $employee->business_id, null)]);
    }

    public function showForSpot(Request $request, SpotEmployee $spotEmployee, ReplaceShifts $shifts): JsonResponse
    {
        $this->ensureOwned($request, $spotEmployee->spot);

        return response()->json(['data' => $shifts->current('spot', $spotEmployee->user_id, $spotEmployee->spot->business_id, $spotEmployee->spot_id)]);
    }

    public function updateForSpot(SaveShiftsRequest $request, SpotEmployee $spotEmployee, ReplaceShifts $shifts): JsonResponse
    {
        $spot = $spotEmployee->spot;
        $this->ensureOwned($request, $spot);

        $shifts->handle('spot', $spotEmployee->user_id, $spot->business_id, $spot->id, $request->days());

        return response()->json(['data' => $shifts->current('spot', $spotEmployee->user_id, $spot->business_id, $spot->id)]);
    }
}
