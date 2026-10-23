<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AppSetting;
use App\Rules\Iban;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform settings: tip fee, plans, support contacts, terms, and the bank
 * account payouts are paid from (the old "App settings" and "Bank payout").
 */
class SettingsController extends AdminController
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->data(AppSetting::current())]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tip_fee_percent' => ['required', 'numeric', 'between:0,50'],
            'tip_fee_fixed' => ['required', 'numeric', 'between:0,10'],
            'user_subscription_required' => ['required', 'boolean'],
            'business_subscription_required' => ['required', 'boolean'],
            'user_plan_monthly' => ['required', 'numeric', 'between:0,1000'],
            'user_plan_yearly' => ['required', 'numeric', 'between:0,10000'],
            'business_plan_monthly' => ['required', 'numeric', 'between:0,1000'],
            'business_plan_yearly' => ['required', 'numeric', 'between:0,10000'],
            'free_trial_days' => ['required', 'integer', 'between:0,365'],
            'support_url' => ['nullable', 'url:http,https', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'terms_and_conditions' => ['nullable', 'string', 'max:200000'],
            'payout_debtor_name' => ['nullable', 'string', 'max:70'],
            'payout_debtor_iban' => ['nullable', 'string', 'max:42', new Iban],
            'payout_debtor_bic' => ['nullable', 'string', 'regex:/^[A-Za-z]{6}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/'],
        ]);

        $validated['payout_debtor_iban'] = filled($validated['payout_debtor_iban'] ?? null) ? strtoupper(str_replace(' ', '', $validated['payout_debtor_iban'])) : null;
        $validated['payout_debtor_bic'] = filled($validated['payout_debtor_bic'] ?? null) ? strtoupper($validated['payout_debtor_bic']) : null;

        $settings = AppSetting::current();
        $settings->update($validated);

        return response()->json(['data' => $this->data($settings)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(AppSetting $settings): array
    {
        return [
            ...$settings->only([
                'tip_fee_percent', 'tip_fee_fixed', 'user_subscription_required', 'business_subscription_required',
                'user_plan_monthly', 'user_plan_yearly', 'business_plan_monthly', 'business_plan_yearly', 'free_trial_days',
                'support_url', 'support_email', 'terms_and_conditions', 'payout_debtor_name', 'payout_debtor_iban', 'payout_debtor_bic',
            ]),
        ];
    }
}
