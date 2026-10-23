<?php

namespace App\Actions\Auth;

use App\Enums\WithdrawalStatus;
use App\Models\SpotEmployee;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes an account. The user row is soft-deleted rather than removed, because
 * tips and payouts must stay in the business's and the bookkeeping's history.
 * Public pages, sign-in and all devices stop working straight away.
 */
class DeleteAccount
{
    /**
     * @throws ValidationException when a payout is still waiting to be transferred
     */
    public function handle(User $user): void
    {
        if ($user->withdrawals()->where('status', WithdrawalStatus::Requested)->exists()) {
            throw ValidationException::withMessages([
                'password' => 'You have a payout that has not been transferred yet. You can delete your account once it is paid.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            $user->business?->forceFill(['is_active' => false])->save();

            SpotEmployee::query()->where('user_id', $user->id)->delete();
            WorkingHour::query()->where('user_id', $user->id)->whereIn('purpose', ['shift', 'spot'])->delete();
            $user->employments()->delete();

            $user->tokens()->delete();
            $user->delete();
        });
    }
}
