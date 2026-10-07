<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case BelumDitagihkan = 'Belum Ditagihkan';
    case SudahDitagihkan = 'Sudah Ditagihkan';
}
