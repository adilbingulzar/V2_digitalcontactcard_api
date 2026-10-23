<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Requests\Business\UpdateSpotStaffRequest;
use App\Http\Resources\SpotResource;
use App\Models\Spot;
use App\Models\WorkingHour;
use Illuminate\Support\Facades\DB;

class SpotStaffController extends BusinessController
{
    /**
     * Set who works at the spot. Members who stay keep their live status and shifts;
     * members who are removed lose their shifts at this spot.
     */
    public function update(UpdateSpotStaffRequest $request, Spot $spot): SpotResource
    {
        $this->ensureOwned($request, $spot);
        $members = collect($request->validated('members'))->keyBy('user_id');

        DB::transaction(function () use ($spot, $members): void {
            $removed = $spot->spotEmployees()->whereNotIn('user_id', $members->keys())->pluck('user_id');
            $spot->spotEmployees()->whereIn('user_id', $removed)->delete();
            WorkingHour::query()->where('spot_id', $spot->id)->whereIn('user_id', $removed)->delete();

            foreach ($members as $userId => $member) {
                $spot->spotEmployees()->updateOrCreate(
                    ['user_id' => $userId],
                    ['business_role_id' => $member['business_role_id'], 'is_active' => true],
                );
            }
        });

        return new SpotResource($spot->load('spotEmployees.user', 'spotEmployees.role'));
    }
}
