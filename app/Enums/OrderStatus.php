<?php

namespace App\Enums;

/** Mirrors src/lib/orderDraft.ts's per-Tipe status lifecycle. */
enum OrderStatus: string
{
    case MenungguSupplierDriver = 'Menunggu Supplier/Driver';
    case SiapKirimWa = 'Siap Kirim WA';
    case InfoTerkirim = 'Info Terkirim';
    case Berlangsung = 'Berlangsung';
    case Selesai = 'Selesai';
    case Dibatalkan = 'Dibatalkan';

    /** @return OrderStatus[] */
    public static function forType(OrderType $type): array
    {
        return $type === OrderType::Ticket
            ? [self::SiapKirimWa, self::InfoTerkirim, self::Berlangsung, self::Selesai]
            : [self::MenungguSupplierDriver, self::SiapKirimWa, self::InfoTerkirim, self::Berlangsung, self::Selesai];
    }

    public static function initial(OrderType $type): self
    {
        return $type === OrderType::Ticket ? self::SiapKirimWa : self::MenungguSupplierDriver;
    }
}
