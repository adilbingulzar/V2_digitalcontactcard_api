<?php

namespace App\Enums;

/**
 * Legacy `tbl_withdrawal.w_status`: 0 = requested, 1 = paid.
 *
 * Tips have no payout status of their own: a tip with no `withdrawal_id` is
 * still in the recipient's balance, and otherwise this status tells whether it
 * was paid out.
 */
enum WithdrawalStatus: string
{
    case Requested = 'requested';
    case Paid = 'paid';
}
