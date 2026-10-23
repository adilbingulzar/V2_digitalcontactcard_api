<?php

namespace App\Enums;

/**
 * Legacy `trans_type`: "online" is a paid tip, "rating" is a review or rating
 * that may come with an amount.
 */
enum TipType: string
{
    case Online = 'online';
    case Rating = 'rating';
}
