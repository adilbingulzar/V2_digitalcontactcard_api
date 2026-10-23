<?php

namespace App\Console\Commands;

use App\Actions\Payouts\RequestPayout;
use App\Models\User;
use App\Models\UserSetting;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Creates payout requests for users with automatic payouts whose schedule is
 * due today: daily every day, weekly on Mondays, monthly on the 1st. Users
 * without a bank account or below the minimum are skipped until next time.
 */
#[Signature('tiqs:automatic-payouts')]
#[Description('Request payouts for users with automatic payouts that are due today')]
class CreateAutomaticPayouts extends Command
{
    public function handle(RequestPayout $requestPayout): int
    {
        $today = CarbonImmutable::now(config('tiqs.business_timezone'));
        $due = array_values(array_filter([
            'daily',
            $today->isMonday() ? 'weekly' : null,
            $today->day === 1 ? 'monthly' : null,
        ]));

        $requested = 0;
        $skipped = 0;

        UserSetting::query()
            ->where('key', 'payout')
            ->where('value', 'auto')
            ->whereIn('sub_value', $due)
            ->with('user')
            ->lazyById()
            ->each(function (UserSetting $setting) use ($requestPayout, &$requested, &$skipped): void {
                if (! $setting->user instanceof User) {
                    return;
                }

                try {
                    $requestPayout->handle($setting->user);
                    $requested++;
                } catch (ValidationException) {
                    $skipped++;
                }
            });

        $this->components->info("Automatic payouts requested: {$requested}, skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
