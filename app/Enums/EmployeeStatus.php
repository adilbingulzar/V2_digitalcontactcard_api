<?php

namespace App\Enums;

/**
 * Legacy `tbl_cl_employee.status`: 0 = pending, 1 = accepted, 2 = rejected.
 */
enum EmployeeStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
