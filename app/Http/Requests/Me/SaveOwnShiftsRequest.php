<?php

namespace App\Http\Requests\Me;

use App\Http\Requests\Business\SaveShiftsRequest;

/**
 * Same hours format as a business setting a member's shifts; whether the
 * user may change these hours is decided by the employee policy.
 */
class SaveOwnShiftsRequest extends SaveShiftsRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
