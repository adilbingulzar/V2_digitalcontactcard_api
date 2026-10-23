<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Business\ReplaceShifts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\SaveOpeningHoursRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Opening hours shown on the user's business card.
 */
class VcardHoursController extends Controller
{
    public function show(Request $request, ReplaceShifts $hours): JsonResponse
    {
        return response()->json(['data' => $hours->current('vcard', $request->user()->id, null, null)]);
    }

    public function update(SaveOpeningHoursRequest $request, ReplaceShifts $hours): JsonResponse
    {
        $userId = $request->user()->id;
        $hours->handle('vcard', $userId, null, null, $request->days());

        return response()->json(['data' => $hours->current('vcard', $userId, null, null)]);
    }
}
