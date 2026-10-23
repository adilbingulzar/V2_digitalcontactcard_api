<?php

namespace App\Http\Requests\Me;

use App\Http\Requests\Business\SaveShiftsRequest;

/**
 * Opening hours on the user's own business card: same shape as shift hours.
 */
class SaveOpeningHoursRequest extends SaveShiftsRequest
{
    /**
     * Every user may have a business card; the controller uses the signed-in user.
     */
    public function authorize(): bool
    {
        return true;
    }
}
