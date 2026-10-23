<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Resources\AgreementResource;
use App\Models\Agreement;
use Illuminate\Http\Request;

class DefaultAgreementController extends BusinessController
{
    /**
     * Make this the agreement new team members get.
     */
    public function store(Request $request, Agreement $agreement): AgreementResource
    {
        $this->ensureOwned($request, $agreement);
        abort_if($agreement->is_for_spots, 422, 'A spot agreement cannot be the default for team members.');

        $this->business($request)->update(['default_agreement_id' => $agreement->id]);

        return new AgreementResource($agreement->load('business'));
    }
}
