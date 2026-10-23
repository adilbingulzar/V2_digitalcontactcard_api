<?php

namespace App\Actions\Admin;

use App\Actions\Notifications\NotifyUser;
use App\Enums\WithdrawalStatus;
use App\Mail\PayoutPaidMail;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Records that the admin transferred payouts, and tells each user (in the app and by email).
 */
class MarkPayoutsPaid
{
    public function __construct(private NotifyUser $notifyUser) {}

    /**
     * @param  list<int>  $withdrawalIds
     * @return int how many payouts were marked as paid (already paid ones are skipped)
     */
    public function handle(array $withdrawalIds): int
    {
        $paid = DB::transaction(function () use ($withdrawalIds) {
            $withdrawals = Withdrawal::query()
                ->whereKey($withdrawalIds)
                ->where('status', WithdrawalStatus::Requested)
                ->with('user')
                ->lockForUpdate()
                ->get();

            foreach ($withdrawals as $withdrawal) {
                $withdrawal->forceFill(['status' => WithdrawalStatus::Paid, 'paid_at' => now()])->save();
            }

            return $withdrawals;
        });

        foreach ($paid as $withdrawal) {
            $this->notifyUser->handle($withdrawal->user_id, 'payment_transfer', [
                'title' => 'Payment transferred',
                'message' => 'Your payout of € '.$withdrawal->amount.' has been transferred to your bank account.',
                'link' => '/dashboard/statement',
                'icon' => 'fa-circle-dollar-to-slot',
            ]);
            Mail::to($withdrawal->user)->send(new PayoutPaidMail($withdrawal));
        }

        return $paid->count();
    }
}
