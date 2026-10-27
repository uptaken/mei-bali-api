<?php

namespace App\Enums;

/**
 * Mirrors the frontend's three-tier permission matrix (src/lib/permissions.ts):
 * Super Admin > Admin > Operator. Operator never creates/edits Orders or sees pricing.
 */
enum UserRole: string
{
    case Operator = 'Operator';
    case Admin = 'Admin';
    case SuperAdmin = 'Super Admin';

    public function canCreateOrder(): bool
    {
        return $this !== self::Operator;
    }

    /** Harga Jual, margin, invoice, tagihan, dan laporan keuangan: Operator tidak boleh melihatnya. */
    public function canSeePricing(): bool
    {
        return $this !== self::Operator;
    }

    /** Harga Jual dan semua angka turunannya (invoice, margin, piutang, laba rugi): hanya Super Admin. */
    public function canSeeHargaJual(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function canApproveCancellation(): bool
    {
        return $this === self::SuperAdmin;
    }
}
