<?php

namespace App\Support;

use App\Enums\OrderSubType;
use App\Models\Order;

/**
 * Template WA untuk order Layanan (Check In, Check Out, Transfer): satu template per sub tipe, nilainya diambil dari
 * detail layanan. Template tersimpan di Master WhatsApp, jadi teksnya bisa diubah tanpa deploy.
 */
class LayananWhatsApp
{
    public const TEMPLATES = [
        'check_in' => [
            'nama' => 'Info Check In',
            'isi' => "Check In - {{tanggal}}\n\nNama Tamu : {{nama_tamu}}\nHotel : {{hotel}}\nFlight : {{flight}}\nTotal : {{jumlah_pax}} pax\n\n*{{jenis_penerbangan}}* - {{catatan}}",
            'variabel' => ['tanggal', 'nama_tamu', 'hotel', 'flight', 'jumlah_pax', 'jenis_penerbangan', 'catatan'],
        ],
        'check_out' => [
            'nama' => 'Info Check Out',
            'isi' => "Check Out - {{tanggal}}\n\nNama Tamu : {{nama_tamu}}\nHotel : {{hotel}}\nJam Jemput : {{jam_jemput}}\nFlight : {{flight}}\nTotal : {{jumlah_pax}} pax\n\n*{{jenis_penerbangan}}* - {{catatan}}",
            'variabel' => ['tanggal', 'nama_tamu', 'hotel', 'jam_jemput', 'flight', 'jumlah_pax', 'jenis_penerbangan', 'catatan'],
        ],
        'transfer' => [
            'nama' => 'Info Transfer',
            'isi' => "Transfer - {{tanggal}}\n\nNama Tamu : {{nama_tamu}}\nPick Up : {{pick_up}}\nDrop Off : {{drop_off}}\nJam Jemput : {{jam_jemput}}\nTotal : {{jumlah_pax}} pax\n\nCatatan : {{catatan}}",
            'variabel' => ['tanggal', 'nama_tamu', 'pick_up', 'drop_off', 'jam_jemput', 'jumlah_pax', 'catatan'],
        ],
    ];

    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

    public static function templateName(?OrderSubType $subTipe): ?string
    {
        return self::TEMPLATES[$subTipe?->value ?? '']['nama'] ?? null;
    }

    /** @return array<string, string> nilai untuk placeholder template sub tipe order ini */
    public static function variables(Order $order): array
    {
        $detail = $order->layananDetail;
        $dash = fn (?string $value) => filled($value) ? $value : '-';
        $date = $order->tanggal_mulai;
        $isCheckIn = $order->sub_tipe === OrderSubType::CheckIn;

        return [
            'tanggal' => $date ? $date->format('j').' '.self::MONTHS[$date->month - 1].' '.$date->year : '-',
            'nama_tamu' => $dash($detail?->nama_tamu),
            'hotel' => $dash($isCheckIn ? $detail?->hotel_drop_off : $detail?->pick_up_point),
            'pick_up' => $dash($detail?->pick_up_point),
            'drop_off' => $dash($detail?->drop_off_point),
            'jam_jemput' => $dash($detail?->jam_jemput),
            'flight' => $dash($detail?->info_flight),
            'jumlah_pax' => (string) ($detail?->jumlah_pax ?? $order->dewasa + $order->anak),
            'jenis_penerbangan' => strtoupper($dash($detail?->jenis_penerbangan)),
            'catatan' => $dash($order->catatan),
        ];
    }
}
