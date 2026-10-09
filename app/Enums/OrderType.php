<?php

namespace App\Enums;

enum OrderType: string
{
    case Tour = 'tour';
    case Layanan = 'layanan';
    case Ticket = 'ticket';
}
