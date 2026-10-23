<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Enums\EmployeeStatus;
use App\Http\Requests\Business\SaveAgreementRequest;
use App\Http\Resources\AgreementResource;
use App\Models\Agreement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Tip-sharing agreements of the business.
 */
class AgreementController extends BusinessController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return AgreementResource::collection(
            $this->business($request)->agreements()
                ->with('business')
                ->withCount(['employees' => fn ($query) => $query->where('status', EmployeeStatus::Accepted)])
                ->latest('id')
                ->get(),
        );
    }

    public function store(SaveAgreementRequest $request): AgreementResource
    {
        $business = $this->business($request);

        $agreement = new Agreement([
            'title' => $request->validated('title'),
            'content' => (string) $request->validated('content'),
            'role_shares' => $request->roleShares(),
            'is_for_spots' => $request->validated('is_for_spots'),
            'is_active' => true,
        ]);
        $agreement->business()->associate($business);
        $agreement->creator()->associate($request->user());
        $agreement->save();

        // The first agreement becomes the default for new team members.
        if ($business->default_agreement_id === null && ! $agreement->is_for_spots) {
            $business->update(['default_agreement_id' => $agreement->id]);
        }

        return new AgreementResource($agreement->load('business'));
    }

    public function update(SaveAgreementRequest $request, Agreement $agreement): AgreementResource
    {
        $this->ensureOwned($request, $agreement);

        $agreement->update([
            'title' => $request->validated('title'),
            'content' => (string) $request->validated('content'),
            'role_shares' => $request->roleShares(),
            'is_for_spots' => $request->validated('is_for_spots'),
        ]);

        return new AgreementResource($agreement->load('business'));
    }

    public function destroy(Request $request, Agreement $agreement): Response
    {
        $this->ensureOwned($request, $agreement);
        $agreement->delete();

        return response()->noContent();
    }
}
