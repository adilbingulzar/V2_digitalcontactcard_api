<?php

namespace App\Enums;

/**
 * Legacy `tbl_cl_users.status`: 0 = email not confirmed, 1 = active,
 * 2 = temporarily deactivated by the user. Legacy status 3 (deleted) maps to
 * a soft delete instead.
 */
enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Deactivated = 'deactivated';
}
