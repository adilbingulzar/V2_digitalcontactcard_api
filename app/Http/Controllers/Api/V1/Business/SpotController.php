<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\Business\CreateNumberedSpots;
use App\Http\Requests\Business\CreateSpotsRequest;
use App\Http\Requests\Business\SaveSpotRequest;
use App\Http\Resources\SpotResource;
use App\Models\Spot;
use App\Models\WorkingHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Spots of the business, each with its own QR code.
 */
class SpotController extends BusinessController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return SpotResource::collection(
            $this->business($request)->spots()->with('spotEmployees.user', 'spotEmployees.role')->orderBy('name')->get(),
        );
    }

    public function bulk(CreateSpotsRequest $request, CreateNumberedSpots $createSpots): JsonResponse
    {
        $spots = $createSpots->handle(
            $this->business($request),
            $request->string('name')->trim()->toString(),
            $request->integer('count'),
            $request->boolean('space'),
        );

        return SpotResource::collection($spots->each->load('spotEmployees'))->response()->setStatusCode(201);
    }

    public function store(SaveSpotRequest $request): SpotResource
    {
        $spot = $this->business($request)->spots()->create($request->validated());

        return new SpotResource($spot->load('spotEmployees'));
    }

    public function update(SaveSpotRequest $request, Spot $spot): SpotResource
    {
        $this->ensureOwned($request, $spot);
        $spot->update($request->validated());

        return new SpotResource($spot->load('spotEmployees.user', 'spotEmployees.role'));
    }

    /**
     * Its tips stay in the reports; they just no longer show a spot.
     */
    public function destroy(Request $request, Spot $spot): Response
    {
        $this->ensureOwned($request, $spot);

        DB::transaction(function () use ($spot): void {
            WorkingHour::query()->where('spot_id', $spot->id)->delete();
            $spot->delete();
        });

        return response()->noContent();
    }
}
