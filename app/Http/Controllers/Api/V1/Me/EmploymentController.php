<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Notifications\NotifyUser;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmploymentResource;
use App\Models\Business;
use App\Models\Employee;
use App\Models\SpotEmployee;
use App\Models\WorkingHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The businesses the signed-in user works at or was invited to ("My work").
 */
class EmploymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return EmploymentResource::collection(
            Employee::query()
                ->whereBelongsTo($request->user())
                ->with('business', 'role', 'agreement')
                ->orderByRaw("FIELD(status, 'pending', 'accepted', 'rejected')")
                ->latest('id')
                ->get(),
        );
    }

    /**
     * Ask to join a business by its code (e.g. BIZ12345678). The owner decides.
     */
    public function store(Request $request): EmploymentResource
    {
        $validated = $request->validate(['business_code' => ['required', 'string', 'max:20']]);
        $user = $request->user();
        $business = Business::query()->where('code', Str::upper(trim($validated['business_code'])))->where('is_active', true)->first();

        if ($business === null) {
            throw ValidationException::withMessages(['business_code' => 'No business was found with this code.']);
        }
        if ($business->owner_id === $user->id) {
            throw ValidationException::withMessages(['business_code' => 'This is your own business.']);
        }

        $employment = $business->employees()->firstOrNew(['user_id' => $user->id]);
        if ($employment->exists && $employment->status !== EmployeeStatus::Rejected) {
            throw ValidationException::withMessages(['business_code' => $employment->status === EmployeeStatus::Accepted
                ? 'You already work at this business.'
                : 'There is already an open request with this business.']);
        }

        $employment->fill(['requested_by' => 'employee', 'reason' => null, 'business_role_id' => null, 'agreement_id' => null, 'is_live' => false]);
        $employment->forceFill(['status' => EmployeeStatus::Pending])->save();

        app(NotifyUser::class)->handle($business->owner_id, 'join_request', [
            'business_id' => $business->id,
            'audience' => 'business',
            'title' => 'Join request',
            'message' => $user->name.' asked to join your team.',
            'link' => '/dashboard/team',
            'icon' => 'fa-user-plus',
        ]);

        return new EmploymentResource($employment->load('business', 'role', 'agreement'));
    }

    /**
     * Leave a business, or withdraw a request. Removes the user from its spots and shifts.
     */
    public function destroy(Request $request, Employee $employee): Response
    {
        abort_unless($employee->user_id === $request->user()->id, 404);

        DB::transaction(function () use ($employee): void {
            SpotEmployee::query()
                ->where('user_id', $employee->user_id)
                ->whereIn('spot_id', $employee->business->spots()->select('id'))
                ->delete();
            WorkingHour::query()
                ->where('user_id', $employee->user_id)
                ->where('business_id', $employee->business_id)
                ->whereIn('purpose', ['shift', 'spot'])
                ->delete();
            $employee->delete();
        });

        return response()->noContent();
    }
}
