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

    public function canSeePricing(): bool
    {
        return $this !== self::Operator;
    }

    public function canApproveCancellation(): bool
    {
        return $this === self::SuperAdmin;
    }
}
