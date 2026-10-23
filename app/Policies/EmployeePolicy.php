<?php

namespace App\Policies;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmployeePolicy
{
    /**
     * Going live (on shift, visible for team tips) is up to the employee
     * themselves, once the business accepted them. Someone else's employment
     * is reported as not found.
     */
    public function setLive(User $user, Employee $employee): Response
    {
        if ($employee->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $employee->status === EmployeeStatus::Accepted
            ? Response::allow()
            : Response::deny('You can go live once the business has accepted you.');
    }

    /**
     * Employees set their own working hours, as in the old app ("Set Working
     * Hours" on the Join Business page), once the business accepted them.
     */
    public function setOwnShifts(User $user, Employee $employee): Response
    {
        if ($employee->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $employee->status === EmployeeStatus::Accepted
            ? Response::allow()
            : Response::deny('You can set your hours once the business has accepted you.');
    }
}
