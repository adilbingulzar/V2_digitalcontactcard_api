<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Notifications\NotifyUser;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmploymentResource;
use App\Models\Employee;
use Illuminate\Http\Request;

/**
 * The signed-in user's answer to a business that invited them.
 */
class InvitationController extends Controller
{
    public function accept(Request $request, Employee $employee): EmploymentResource
    {
        $this->ensureOpenInvitation($request, $employee);

        $employee->forceFill(['status' => EmployeeStatus::Accepted])->save();
        $this->notifyOwner($employee, 'accept_join_request', $request->user()->name.' joined your team.');

        return new EmploymentResource($employee->load('business', 'role', 'agreement'));
    }

    public function decline(Request $request, Employee $employee): EmploymentResource
    {
        $this->ensureOpenInvitation($request, $employee);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $employee->fill(['reason' => $validated['reason'] ?? null]);
        $employee->forceFill(['status' => EmployeeStatus::Rejected])->save();
        // The old app called this rejecting the business agreement.
        $this->notifyOwner($employee, 'reject_agreement', $request->user()->name.' declined your invitation.');

        return new EmploymentResource($employee->load('business', 'role', 'agreement'));
    }

    private function ensureOpenInvitation(Request $request, Employee $employee): void
    {
        abort_unless($employee->user_id === $request->user()->id, 404);
        abort_unless($employee->status === EmployeeStatus::Pending && $employee->requested_by === 'business', 422, 'This is not an open invitation.');
    }

    private function notifyOwner(Employee $employee, string $type, string $message): void
    {
        app(NotifyUser::class)->handle($employee->business->owner_id, $type, [
            'business_id' => $employee->business_id,
            'audience' => 'business',
            'title' => 'Team',
            'message' => $message,
            'link' => '/dashboard/team',
            'icon' => 'fa-user-check',
        ]);
    }
}
