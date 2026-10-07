<?php

namespace App\Enums;

enum OrderSubType: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
    case Transfer = 'transfer';
}
