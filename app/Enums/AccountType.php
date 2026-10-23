<?php

namespace App\Enums;

/**
 * Legacy `tbl_cl_users.type`: 1 = private (waiter, staff), 2 = business.
 */
enum AccountType: string
{
    case Private = 'private';
    case Business = 'business';
}
