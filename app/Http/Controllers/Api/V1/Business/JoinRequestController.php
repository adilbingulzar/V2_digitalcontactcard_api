<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\Notifications\NotifyUser;
use App\Enums\EmployeeStatus;
use App\Http\Requests\Business\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;

/**
 * The owner's answer to someone who asked to join the business.
 */
class JoinRequestController extends BusinessController
{
    public function accept(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $this->ensurePendingRequest($request, $employee);
        $business = $this->business($request);

        $employee->fill([
            'business_role_id' => $request->validated('business_role_id'),
            'agreement_id' => $request->validated('agreement_id') ?? $business->default_agreement_id,
        ]);
        $employee->forceFill(['status' => EmployeeStatus::Accepted])->save();

        $this->notify($employee, 'accept_join_request', $business->name.' accepted your request to join the team.');

        return new EmployeeResource($employee->load('user', 'role', 'agreement'));
    }

    public function reject(Request $request, Employee $employee): EmployeeResource
    {
        $this->ensurePendingRequest($request, $employee);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $employee->fill(['reason' => $validated['reason'] ?? null]);
        $employee->forceFill(['status' => EmployeeStatus::Rejected])->save();

        $this->notify($employee, 'decline_join_request', $this->business($request)->name.' declined your request to join the team.');

        return new EmployeeResource($employee->load('user', 'role', 'agreement'));
    }

    private function ensurePendingRequest(Request $request, Employee $employee): void
    {
        $this->ensureOwned($request, $employee);
        abort_unless($employee->status === EmployeeStatus::Pending && $employee->requested_by === 'employee', 422, 'This is not an open join request.');
    }

    private function notify(Employee $employee, string $type, string $message): void
    {
        app(NotifyUser::class)->handle($employee->user_id, $type, [
            'business_id' => $employee->business_id,
            'audience' => 'user',
            'title' => $employee->business->name,
            'message' => $message,
            'link' => '/dashboard/work',
            'icon' => 'fa-briefcase',
        ]);
    }
}
