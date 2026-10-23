<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Business\ReplaceShifts;
use App\Http\Controllers\Controller;
use App\Models\SpotEmployee;
use App\Models\WorkingHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * The spots a business put the signed-in user on (the old "Spot for task"
 * page). While live at a spot, the user shares in the tips given there.
 */
class WorkSpotController extends Controller
{
    public function index(Request $request, ReplaceShifts $shifts): JsonResponse
    {
        $assignments = SpotEmployee::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->whereHas('spot', fn ($spots) => $spots->where('is_active', true))
            ->with('spot.business:id,name', 'role:id,name')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $assignments->map(fn (SpotEmployee $assignment): array => [
            'id' => $assignment->id,
            'spot' => ['id' => $assignment->spot->id, 'name' => $assignment->spot->name, 'slug' => $assignment->spot->slug],
            'business' => ['id' => $assignment->spot->business->id, 'name' => $assignment->spot->business->name],
            'role' => $assignment->role?->name,
            'is_live' => $assignment->is_live,
            'shifts' => $shifts->current('spot', $assignment->user_id, $assignment->spot->business_id, $assignment->spot_id),
        ])->all()]);
    }

    /**
     * Going live at one spot ends being live at any other spot, as in the old app.
     */
    public function live(Request $request, SpotEmployee $spotEmployee): JsonResponse
    {
        $this->ensureOwn($request, $spotEmployee);
        $validated = $request->validate(['is_live' => ['required', 'boolean']]);

        DB::transaction(function () use ($spotEmployee, $validated): void {
            if ($validated['is_live']) {
                SpotEmployee::query()->where('user_id', $spotEmployee->user_id)->whereKeyNot($spotEmployee->id)->update(['is_live' => false]);
            }
            $spotEmployee->forceFill(['is_live' => $validated['is_live']])->save();
        });

        return response()->json(['data' => ['id' => $spotEmployee->id, 'is_live' => $spotEmployee->is_live]]);
    }

    /**
     * Leave a spot: also removes the shift hours there.
     */
    public function destroy(Request $request, SpotEmployee $spotEmployee): Response
    {
        $this->ensureOwn($request, $spotEmployee);

        DB::transaction(function () use ($spotEmployee): void {
            WorkingHour::query()
                ->where('purpose', 'spot')
                ->where('user_id', $spotEmployee->user_id)
                ->where('spot_id', $spotEmployee->spot_id)
                ->delete();
            $spotEmployee->delete();
        });

        return response()->noContent();
    }

    private function ensureOwn(Request $request, SpotEmployee $spotEmployee): void
    {
        abort_unless($spotEmployee->user_id === $request->user()->id && $spotEmployee->is_active, 404);
    }
}
