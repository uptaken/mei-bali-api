<?php

namespace App\Support;

use App\Enums\OrderSubType;
use App\Models\Duration;
use App\Models\Language;
use App\Models\Order;
use App\Models\Package;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Carbon;

/**
 * Pesan WA ke supplier/driver untuk satu order. Tour dan tiap sub tipe Layanan (Check In, Check Out, Transfer) punya
 * template sendiri; tipe lain memakai "Konfirmasi Supplier". Template tersimpan di Master WhatsApp, jadi teksnya bisa
 * diubah tanpa deploy. Satu pintu ini dipakai untuk pratinjau dan pengiriman supaya isinya selalu sama.
 */
class OrderWhatsApp
{
    public const FALLBACK_TEMPLATE = 'Konfirmasi Supplier';

    public const TEMPLATES = [
        'tour' => [
            'nama' => 'Info Tour',
            'isi' => "{{nama_paket}}\n{{tanggal}}\n{{bahasa}}\n\n{{catatan_order}}\n\nNama supir : {{nama_supir}}\nNama Tamu : {{nama_tamu}}\nTotal : {{jumlah_pax}}\n\nRute :\n{{rute}}\n\n{{catatan_penugasan}}",
            'variabel' => ['nama_paket', 'tanggal', 'bahasa', 'catatan_order', 'nama_supir', 'nama_tamu', 'jumlah_pax', 'rute', 'catatan_penugasan'],
        ],
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

    public static function templateName(Order $order): string
    {
        $key = $order->tipe->value === 'tour' ? 'tour' : ($order->tipe->value === 'layanan' ? $order->sub_tipe?->value : null);

        return self::TEMPLATES[$key ?? '']['nama'] ?? self::FALLBACK_TEMPLATE;
    }

    /**
     * @return array{template: string, message: ?string} `message` is null when the template is missing from Master
     */
    public static function build(Order $order, ?Supplier $supplier, ?Vehicle $vehicle): array
    {
        $order->loadMissing(['itineraryDays.activities', 'guests', 'layananDetail']);
        $name = self::templateName($order);
        $template = WhatsAppTemplate::where('nama', $name)->first();

        if (! $template) {
            return ['template' => $name, 'message' => null];
        }

        $variables = match ($name) {
            self::FALLBACK_TEMPLATE => self::confirmationVariables($order, $supplier, $vehicle),
            self::TEMPLATES['tour']['nama'] => self::tourVariables($order, $supplier),
            default => self::layananVariables($order),
        };

        // Variabel opsional yang kosong meninggalkan baris kosong; rapikan supaya pesan tidak bolong.
        $message = trim(preg_replace("/\n{3,}/", "\n\n", $template->render($variables)));

        return ['template' => $name, 'message' => $message];
    }

    private static function date(?Carbon $date): string
    {
        return $date ? $date->format('j').' '.self::MONTHS[$date->month - 1].' '.$date->year : '-';
    }

    /** "17 - 19 Agt 2026", "30 Agt - 2 Sep 2026", atau satu tanggal bila hanya satu hari. */
    private static function dateRange(?Carbon $start, int $days): string
    {
        if (! $start || $days <= 1) {
            return self::date($start);
        }

        $end = $start->copy()->addDays($days - 1);
        if ($start->isSameMonth($end) && $start->year === $end->year) {
            return $start->day.' - '.self::date($end);
        }

        return $start->day.' '.self::MONTHS[$start->month - 1].($start->year === $end->year ? '' : ' '.$start->year).' - '.self::date($end);
    }

    private static function confirmationVariables(Order $order, ?Supplier $supplier, ?Vehicle $vehicle): array
    {
        $jemput = match (true) {
            $order->tipe->value === 'tour' => $order->itineraryDays->first()?->tempat_penjemputan ?? $order->destinasi ?? '-',
            $order->tipe->value === 'ticket' => $order->kota ?? $order->nama_order ?? '-',
            $order->sub_tipe === OrderSubType::CheckIn => $order->layananDetail?->hotel_drop_off ?? $order->kota ?? '-',
            default => $order->layananDetail?->pick_up_point ?? $order->kota ?? '-',
        };

        return [
            'nama_supplier' => $supplier?->nama ?? '(supplier)',
            'kode_order' => $order->kode,
            'tanggal' => self::date($order->tanggal_mulai),
            'jam' => $order->jam_mulai ?? '-',
            'tempat_jemput' => $jemput,
            'jumlah_tamu' => ($order->dewasa + $order->anak).' pax',
            'tipe_mobil' => $vehicle?->nama ?? '(mobil)',
        ];
    }

    private static function tourVariables(Order $order, ?Supplier $supplier): array
    {
        $days = $order->itineraryDays->sortBy('hari')->values();
        $durations = Duration::whereIn('id', $days->pluck('durasi_id')->filter())->pluck('label', 'id');
        $note = fn (?string $text) => filled($text) ? "*({$text})*" : '';

        $rute = $days->map(function ($day) use ($order, $durations) {
            $lines = [self::date($order->tanggal_mulai?->copy()->addDays($day->hari - 1))];
            if ($label = $durations[$day->durasi_id] ?? null) {
                $lines[] = "*({$label})*";
            }
            if (filled($day->tempat_penjemputan)) {
                $lines[] = "* Jemput di {$day->tempat_penjemputan}";
                if (filled($day->waktu_penjemputan)) {
                    $lines[] = 'Jam '.str_replace(':', '.', $day->waktu_penjemputan);
                }
            }
            foreach ($day->activities->sortBy('urutan') as $activity) {
                $lines[] = "* {$activity->aktivitas}";
            }
            if (filled($day->tempat_drop_akhir)) {
                $lines[] = "* Drop {$day->tempat_drop_akhir}";
            }
            if (filled($day->catatan)) {
                $lines[] = "Catatan: {$day->catatan}";
            }

            return implode("\n", $lines);
        })->implode("\n\n");

        $adults = $order->dewasa.' adl';
        $bahasa = $order->bahasa_id ? Language::find($order->bahasa_id)?->nama : null;

        return [
            'nama_paket' => (($order->paket_id ? Package::find($order->paket_id)?->nama : null) ?: $order->nama_order) ?: '-',
            'tanggal' => self::dateRange($order->tanggal_mulai, max(1, $days->count() ?: ($order->durasi_hari ?? 1))),
            'bahasa' => filled($bahasa) ? '('.strtoupper($bahasa).')' : '',
            'catatan_order' => $note($order->catatan),
            'nama_supir' => $supplier ? ($supplier->nama_driver ?: $supplier->nama) : '-',
            'nama_tamu' => $order->guests->pluck('nama')->filter()->implode("\n") ?: '-',
            'jumlah_pax' => $order->anak > 0 ? "{$adults} + {$order->anak} chd" : $adults,
            'rute' => $rute ?: '-',
            'catatan_penugasan' => $note($order->catatan_penugasan),
        ];
    }

    private static function layananVariables(Order $order): array
    {
        $detail = $order->layananDetail;
        $dash = fn (?string $value) => filled($value) ? $value : '-';
        $isCheckIn = $order->sub_tipe === OrderSubType::CheckIn;

        return [
            'tanggal' => self::date($order->tanggal_mulai),
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
