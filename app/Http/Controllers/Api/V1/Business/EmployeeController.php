<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\Notifications\NotifyUser;
use App\Enums\EmployeeStatus;
use App\Enums\UserStatus;
use App\Http\Requests\Business\InviteEmployeeRequest;
use App\Http\Requests\Business\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\SpotEmployee;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The business owner's team: members, invitations sent and join requests received.
 */
class EmployeeController extends BusinessController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return EmployeeResource::collection(
            $this->business($request)->employees()
                ->with('user', 'role', 'agreement')
                ->whereHas('user')
                ->orderByRaw("FIELD(status, 'pending', 'accepted', 'rejected')")
                ->latest('id')
                ->get(),
        );
    }

    /**
     * Invite a user by email or public URL name. They get a notification and decide.
     */
    public function store(InviteEmployeeRequest $request): EmployeeResource
    {
        $business = $this->business($request);
        $identifier = trim($request->validated('identifier'));

        $user = User::query()
            ->where('status', UserStatus::Active)
            ->where(fn ($query) => $query->where('email', Str::lower($identifier))->orWhere('slug', $identifier))
            ->first();

        if ($user === null || $user->id === $business->owner_id) {
            throw ValidationException::withMessages(['identifier' => 'No active account was found with this email or public name.']);
        }

        $employee = $business->employees()->firstOrNew(['user_id' => $user->id]);

        if ($employee->exists && $employee->status !== EmployeeStatus::Rejected) {
            throw ValidationException::withMessages(['identifier' => $employee->status === EmployeeStatus::Accepted
                ? 'This person is already in your team.'
                : 'There is already an open request for this person.']);
        }

        $employee->fill([
            'business_role_id' => $request->validated('business_role_id'),
            'agreement_id' => $request->validated('agreement_id') ?? $business->default_agreement_id,
            'requested_by' => 'business',
            'reason' => null,
            'is_live' => false,
        ]);
        $employee->forceFill(['status' => EmployeeStatus::Pending])->save();

        app(NotifyUser::class)->handle($user->id, 'join_request', [
            'business_id' => $business->id,
            'audience' => 'user',
            'title' => $business->name,
            'message' => $business->name.' invited you to join their team.',
            'link' => '/dashboard/work',
            'icon' => 'fa-briefcase',
        ]);

        return new EmployeeResource($employee->load('user', 'role', 'agreement'));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $this->ensureOwned($request, $employee);

        $employee->update($request->validated());

        return new EmployeeResource($employee->load('user', 'role', 'agreement'));
    }

    /**
     * Remove a member (or withdraw an invitation): also from the business's spots and shifts.
     */
    public function destroy(Request $request, Employee $employee): Response
    {
        $this->ensureOwned($request, $employee);
        $business = $this->business($request);

        DB::transaction(function () use ($employee, $business): void {
            SpotEmployee::query()
                ->where('user_id', $employee->user_id)
                ->whereIn('spot_id', $business->spots()->select('id'))
                ->delete();
            WorkingHour::query()
                ->where('user_id', $employee->user_id)
                ->where('business_id', $business->id)
                ->whereIn('purpose', ['shift', 'spot'])
                ->delete();
            $employee->delete();
        });

        return response()->noContent();
    }
}
