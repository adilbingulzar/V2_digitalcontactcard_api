<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Payouts\CalculatePayout;
use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayoutSettingController extends Controller
{
    /**
     * Payout schedule (manual, or automatic daily/weekly/monthly) and who pays
     * the platform fee on tips (stored as the old app's "payout" and
     * "tax_deduction" settings).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule' => ['required', Rule::in(CalculatePayout::SCHEDULES)],
            'platform_fee_paid_by' => ['required', Rule::in(['recipient', 'customer'])],
        ]);
        $user = $request->user();

        UserSetting::query()->updateOrCreate(
            ['user_id' => $user->id, 'key' => 'payout'],
            [
                'value' => $validated['schedule'] === 'manual' ? 'manual' : 'auto',
                'sub_value' => $validated['schedule'] === 'manual' ? null : $validated['schedule'],
            ],
        );
        UserSetting::query()->updateOrCreate(
            ['user_id' => $user->id, 'key' => 'tax_deduction'],
            ['value' => $validated['platform_fee_paid_by'] === 'customer' ? '1' : '0'],
        );

        return response()->json(['data' => $validated]);
    }
}
