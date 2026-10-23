<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Base of the business owner's endpoints (/me/business/…). Every record they
 * touch must belong to the signed-in user's own business; anything else is
 * reported as not found, so one business cannot even learn another's records exist.
 */
abstract class BusinessController extends Controller
{
    protected function business(Request $request): Business
    {
        $business = $request->user()->business;

        abort_if($business === null, 403, 'Only business accounts can do this.');

        return $business;
    }

    /**
     * @param  Model&object{business_id: int}  $model
     */
    protected function ensureOwned(Request $request, Model $model): void
    {
        abort_unless($model->getAttribute('business_id') === $this->business($request)->id, 404);
    }
}
