<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates and edits an Order together with everything that hangs off it (guests, assignments,
 * itinerary, add-ons, ticket rows, service detail) and keeps its one Invoice in step.
 *
 * Ported from the Review & Konfirmasi flow (src/pages/orders/OrderReview.tsx). Modal is the only
 * figure computed here — Harga Jual is always set by hand on the Invoice, so editing an Order never
 * resets it. Payment state (bayar_status / tanggal_bayar) is owned by the Payables endpoints and is
 * never overwritten by an edit.
 */
class OrderService
{
    /** API field => orders column, for the plain fields the frontend sends. */
    private const COLUMNS = [
        'subTipe' => 'sub_tipe',
        'clientId' => 'client_id',
        'namaOrder' => 'nama_order',
        'kodeGroup' => 'kode_group',
        'tanggalMulai' => 'tanggal_mulai',
        'jamMulai' => 'jam_mulai',
        'durasiHari' => 'durasi_hari',
        'destinasi' => 'destinasi',
        'bahasaId' => 'bahasa_id',
        'kota' => 'kota',
        'kotaTermasuk' => 'kota_termasuk',
        'paketId' => 'paket_id',
        'dewasa' => 'dewasa',
        'anak' => 'anak',
        'catatan' => 'catatan',
        'tanggalPemakaian' => 'tanggal_pemakaian',
    ];

    /** Labels of the single Layanan main invoice line, keyed by sub-tipe. */
    private const LAYANAN_LABELS = ['check_in' => 'Check In', 'check_out' => 'Check Out', 'transfer' => 'Transfer', 'default' => 'Layanan'];

    /** Relations needed to compute totals, status and invoice lines. */
    private const RELATIONS = [
        'assignments.vehicle', 'assignments.supplier', 'itineraryDays.activities',
        'addOns.product', 'ticketRows', 'layananDetail',
    ];

    public function __construct(
        private readonly NumberingService $numbering,
    ) {}

    public function create(array $data, User $creator): Order
    {
        return DB::transaction(function () use ($data, $creator) {
            $tipe = OrderType::from($data['tipe']);

            $order = Order::create([
                'dewasa' => 0,
                'anak' => 0,
                'total' => 0,
                ...$this->columns($data),
                'kode' => $this->numbering->nextOrderCode(),
                'tipe' => $tipe->value,
                'status' => OrderStatus::initial($tipe)->value,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ]);

            $this->clearLeftovers($order);
            $this->saveChildren($order, $data);
            $this->refreshDerivedFields($order);

            $events = [['kind' => 'created'], ['kind' => 'confirmed']];
            $supplierNames = $this->assignedSupplierNames($order);
            if ($this->costedAssignments($order)->isNotEmpty()) {
                $events[] = ['kind' => 'assigned', 'detail' => $supplierNames ?: null];
            }
            $this->logEvents($order->events(), $creator, $events);

            $this->syncInvoice($order, $creator, $supplierNames);

            return $order;
        });
    }

    /**
     * A brand-new Order cannot have children yet. Rows can still exist for its id when an earlier Order
     * was removed straight from the database without its children (foreign keys off, or a truncate) and
     * the id was handed out again — they would show up as another Order's Riwayat, tamu or invoice.
     */
    private function clearLeftovers(Order $order): void
    {
        $order->itineraryDays->each(fn ($day) => $day->activities()->delete());
        $order->invoices->each(function ($invoice) {
            $invoice->lines()->delete();
            $invoice->events()->delete();
        });

        foreach (['events', 'assignments', 'guests', 'itineraryDays', 'addOns', 'ticketRows', 'layananDetail', 'invoices'] as $relation) {
            $order->{$relation}()->delete();
        }
    }

    public function update(Order $order, array $data, User $actor): Order
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            $assignedBefore = $this->assignmentSignature($order);
            $before = $this->snapshotData($order);

            // tipe and subTipe are locked once the Order exists.
            $order->update(Arr::except($this->columns($data), 'sub_tipe'));

            $this->saveChildren($order, $data);
            $this->refreshDerivedFields($order);

            $supplierNames = $this->assignedSupplierNames($order);
            $invoice = $this->syncInvoice($order, $actor, $supplierNames);

            // Pressing Ubah and saving without changing anything is not an edit: no Riwayat entry, no "diperbarui oleh".
            $after = $this->snapshotData($order);
            if ($after == $before) {
                return $order;
            }

            $order->update(['updated_by' => $actor->id]);

            // Giving a Supplier/Driver (or changing who it is) is worth its own Riwayat entry, on the Order and on its Invoice.
            // Picking only a vehicle, or changing a cost, is an ordinary edit: "Supplier/Driver ditugaskan" would be untrue.
            if ($this->assignmentSignature($order) !== $assignedBefore && $supplierNames !== '') {
                $assigned = [['kind' => 'assigned', 'detail' => $supplierNames ?: null]];
                $this->logEvents($order->events(), $actor, $assigned);
                $this->logEvents($invoice->events(), $actor, $assigned);
                // Bagian lain yang ikut berubah pada simpan yang sama tetap dicatat sebagai perubahan biasa.
                $others = $this->describeChanges($before, $after, withAssignments: false);
                if ($others !== '') {
                    $this->logEvents($order->events(), $actor, [['kind' => 'updated', 'detail' => $others]]);
                }
            } else {
                $this->logEvents($order->events(), $actor, [['kind' => 'updated', 'detail' => $this->describeChanges($before, $after) ?: null]]);
            }

            return $order;
        });
    }

    /**
     * What an Order holds right now — its own columns plus every child row — without ids and timestamps, so two
     * snapshots are equal exactly when an edit changed nothing (rows that were deleted and re-created count as equal).
     */
    private function snapshotData(Order $order): array
    {
        $order = $order->fresh(['guests', 'assignments', 'itineraryDays.activities', 'addOns', 'ticketRows', 'layananDetail']);
        $noise = ['id', 'order_id', 'order_itinerary_day_id', 'created_at', 'updated_at'];
        $rows = fn ($models) => $models->map(fn ($m) => Arr::except($m->getAttributes(), $noise))->values()->all();

        // The form sends 0 / [] for fields the database holds as NULL; those are the same "empty".
        $own = Arr::except($order->getAttributes(), ['id', 'created_at', 'updated_at', 'updated_by']);
        $own['durasi_hari'] = $own['durasi_hari'] ?: null;
        $own['kota_termasuk'] = in_array($own['kota_termasuk'], ['', '[]'], true) ? null : $own['kota_termasuk'];

        return [
            'order' => $own,
            'guests' => $rows($order->guests),
            'assignments' => $rows($order->assignments),
            'days' => $order->itineraryDays->map(fn ($day) => [...Arr::except($day->getAttributes(), $noise), 'activities' => $rows($day->activities)])->values()->all(),
            'addOns' => $rows($order->addOns),
            'ticketRows' => $rows($order->ticketRows),
            'layanan' => $order->layananDetail ? Arr::except($order->layananDetail->getAttributes(), $noise) : null,
        ];
    }

    /** Kolom order yang dilaporkan di Riwayat, dengan nama yang dikenal pengguna. Kolom turunan/sistem (status, total) tidak dilaporkan. */
    private const OWN_LABELS = [
        'client_id' => 'Client', 'nama_order' => 'Nama Order', 'kode_group' => 'Kode Group', 'tanggal_mulai' => 'Tanggal Mulai',
        'tanggal_pemakaian' => 'Tanggal Pemakaian', 'jam_mulai' => 'Jam Mulai', 'durasi_hari' => 'Durasi', 'destinasi' => 'Destinasi',
        'bahasa_id' => 'Bahasa', 'kota' => 'Kota', 'kota_termasuk' => 'Kota Termasuk', 'paket_id' => 'Paket', 'dewasa' => 'Jumlah Dewasa',
        'anak' => 'Jumlah Anak', 'catatan' => 'Catatan', 'catatan_penugasan' => 'Catatan Penugasan',
    ];

    /** Kolom pendek yang nilai lamanya layak ditampilkan ("Nama Order: A → B"); sisanya cukup nama kolomnya. */
    private const SHOW_VALUES = ['nama_order', 'kode_group', 'tanggal_mulai', 'tanggal_pemakaian', 'jam_mulai', 'durasi_hari', 'destinasi', 'kota', 'dewasa', 'anak'];

    /**
     * Ringkasan perubahan untuk Riwayat, mis. "Nama Order: A → B, Tanggal Mulai: 2026-11-20 → 2026-11-25, Itinerary Hari 2".
     * Angka uang (modal, biaya) hanya disebut namanya, tidak nilainya.
     */
    private function describeChanges(array $before, array $after, bool $withAssignments = true): string
    {
        $show = fn ($v) => $v === null || $v === '' ? '(kosong)' : (string) $v;
        $labels = [];

        foreach (self::OWN_LABELS as $column => $label) {
            $old = $before['order'][$column] ?? null;
            $new = $after['order'][$column] ?? null;
            if (($old ?? '') == ($new ?? '')) {
                continue;
            }
            $labels[] = in_array($column, self::SHOW_VALUES, true) ? "{$label}: {$show($old)} → {$show($new)}" : $label;
        }

        if ($before['guests'] != $after['guests']) {
            $labels[] = 'Nama Tamu';
        }

        if ($withAssignments) {
            $pick = fn (array $rows, array $keys) => array_map(fn ($r) => Arr::only($r, $keys), $rows);
            if ($pick($before['assignments'], ['hari', 'vehicle_id', 'supplier_id']) != $pick($after['assignments'], ['hari', 'vehicle_id', 'supplier_id'])) {
                $labels[] = 'Penugasan Mobil/Supplier/Driver';
            }
            if ($pick($before['assignments'], ['hari', 'biaya_transport_modal']) != $pick($after['assignments'], ['hari', 'biaya_transport_modal'])) {
                $labels[] = 'Biaya Transport';
            }
        }

        if (count($before['days']) !== count($after['days'])) {
            $labels[] = 'Jumlah Hari Itinerary';
        }
        foreach ($after['days'] as $i => $day) {
            $old = $before['days'][$i] ?? null;
            if ($old === null) {
                continue;
            }
            $hari = $day['hari'] ?? $i + 1;
            $dayOnly = fn (array $d) => Arr::except($d, ['activities']);
            if ($dayOnly($old) != $dayOnly($day)) {
                $labels[] = "Itinerary Hari {$hari}";
            }
            $acts = fn (array $d, array $keys) => array_map(fn ($a) => Arr::only($a, $keys), $d['activities']);
            if ($acts($old, ['aktivitas']) != $acts($day, ['aktivitas'])) {
                $labels[] = "Aktivitas Hari {$hari}";
            }
            if ($acts($old, ['biaya', 'skip_biaya']) != $acts($day, ['biaya', 'skip_biaya'])) {
                $labels[] = "Modal Tur Hari {$hari}";
            }
            if ($acts($old, ['supplier_tur']) != $acts($day, ['supplier_tur'])) {
                $labels[] = "Supplier Tur Hari {$hari}";
            }
        }

        if ($before['addOns'] != $after['addOns']) {
            $labels[] = 'Add-Ons';
        }
        if ($before['ticketRows'] != $after['ticketRows']) {
            $labels[] = 'Tiket';
        }
        if ($before['layanan'] != $after['layanan']) {
            $labels[] = 'Detail Layanan';
        }

        return Str::limit(implode(', ', $labels), 250, '…');
    }

    /** Maps the plain API fields that were actually sent onto orders columns. */
    private function columns(array $data): array
    {
        $columns = [];
        foreach (self::COLUMNS as $field => $column) {
            if (array_key_exists($field, $data)) {
                $columns[$column] = $data[$field];
            }
        }

        // Durasi 0 dari form berarti "tidak ada", bukan nol hari.
        if (array_key_exists('durasi_hari', $columns) && ! $columns['durasi_hari']) {
            $columns['durasi_hari'] = null;
        }

        // Hanya Tour yang punya itinerary; itinerary kosong (Layanan/Ticket) tidak boleh menghasilkan durasi 0.
        if (! empty($data['itinerary']) && ! isset($columns['durasi_hari'])) {
            $columns['durasi_hari'] = count($data['itinerary']);
        }

        // Order Layanan: jam order mengikuti jam jemput bila tidak dikirim terpisah (kolom Jam di daftar order).
        if (! isset($columns['jam_mulai']) && ! empty($data['layananDetail']['jamJemput'])) {
            $columns['jam_mulai'] = $data['layananDetail']['jamJemput'];
        }

        // dewasa / anak are NOT NULL, but the frontend may send null for an untouched field.
        foreach (['dewasa', 'anak'] as $column) {
            if (array_key_exists($column, $columns)) {
                $columns[$column] ??= 0;
            }
        }

        return $columns;
    }

    /** Saves each child collection that was sent; collections left out of the payload stay untouched. */
    private function saveChildren(Order $order, array $data): void
    {
        if (array_key_exists('tamu', $data)) {
            $order->guests()->delete();
            $order->guests()->createMany(array_map(
                fn ($g) => ['nama' => $g['nama'] ?? '', 'kategori' => $g['kategori']],
                $data['tamu'],
            ));
        }

        if (array_key_exists('assignments', $data)) {
            $this->syncRows($order->assignments(), $data['assignments'], fn ($a) => [
                'hari' => $a['hari'] ?? 1,
                'vehicle_id' => $a['vehicleId'] ?? null,
                'supplier_id' => $a['supplierId'] ?? null,
                'biaya_transport_modal' => $a['biayaTransportModal'] ?? 0,
            ]);
        }

        if (array_key_exists('itinerary', $data)) {
            $this->saveItinerary($order, $data['itinerary']);
        }

        if (array_key_exists('addOns', $data)) {
            $this->syncRows($order->addOns(), $data['addOns'], fn ($a) => [
                'product_id' => $a['produkId'] ?? null,
                'qty' => $a['qty'] ?? 1,
                'modal' => $a['modal'] ?? 0,
                'catatan' => $a['catatan'] ?? null,
                'skip_biaya' => $a['skipBiaya'] ?? false,
                'supplier_nama' => $a['supplierNama'] ?? null,
            ]);
        }

        if (array_key_exists('ticketRows', $data)) {
            $this->syncRows($order->ticketRows(), $data['ticketRows'], fn ($r) => [
                'tiket_id' => $r['tiketId'] ?? null,
                'nama_tiket' => $r['namaTiket'],
                'qty' => $r['qty'] ?? 1,
                'modal_satuan' => $r['modalSatuan'] ?? 0,
                'harga_jual_ref' => $r['hargaJualRef'] ?? 0,
                'supplier_nama' => $r['supplierNama'] ?? null,
            ]);
        }

        if ($order->tipe === OrderType::Layanan && isset($data['layananDetail'])) {
            $detail = $data['layananDetail'];
            $order->layananDetail()->updateOrCreate([], [
                'nama_tamu' => $detail['namaTamu'] ?? '',
                'hotel_drop_off' => $detail['hotelDropOff'] ?? null,
                'pick_up_point' => $detail['pickUpPoint'] ?? null,
                'drop_off_point' => $detail['dropOffPoint'] ?? null,
                'jam_jemput' => $detail['jamJemput'] ?? null,
                'info_flight' => $detail['infoFlight'] ?? null,
                'jumlah_pax' => $detail['jumlahPax'] ?? 1,
                'biaya_modal' => $detail['biayaModal'] ?? 0,
                'supplier_nama' => $detail['supplierNama'] ?? null,
            ]);
        }
    }

    /** Days are matched by `hari`, activities by `id`, so edits keep each activity's payment state. */
    private function saveItinerary(Order $order, array $days): void
    {
        $days = array_values($days);
        $savedDays = $this->syncRows($order->itineraryDays(), $days, fn ($day) => [
            'hari' => $day['hari'],
            'waktu_penjemputan' => $day['waktuPenjemputan'] ?? null,
            'tempat_penjemputan' => $day['tempatPenjemputan'] ?? null,
            'waktu_drop_akhir' => $day['waktuDropAkhir'] ?? null,
            'tempat_drop_akhir' => $day['tempatDropAkhir'] ?? null,
            'catatan' => $day['catatan'] ?? null,
        ], matchBy: 'hari');

        foreach ($savedDays as $index => $day) {
            $this->syncRows($day->activities(), $days[$index]['aktivitas'] ?? [], fn ($activity, $position) => [
                'aktivitas' => $activity['aktivitas'] ?? '',
                'biaya' => $activity['biaya'] ?? 0,
                'skip_biaya' => $activity['skipBiaya'] ?? false,
                'supplier_tur' => $activity['supplierTur'] ?? null,
                'urutan' => $position,
            ]);
        }
    }

    /**
     * Makes $relation hold exactly $rows: a row whose $matchBy value matches an existing record
     * updates it in place, new rows are created, and records no longer sent are deleted. Updating in
     * place (instead of delete + recreate) is what keeps columns this service does not own.
     *
     * @param  Closure(array, int): array  $attributes  builds the columns to save for one row
     * @return Collection<int, Model> saved models, in the order of $rows
     */
    private function syncRows(HasMany $relation, array $rows, Closure $attributes, string $matchBy = 'id'): Collection
    {
        $existing = $relation->get()->keyBy($matchBy);
        $saved = collect();

        foreach (array_values($rows) as $position => $row) {
            $model = $existing->get($row[$matchBy] ?? null);

            if ($model) {
                $model->update($attributes($row, $position));
            } else {
                $model = $relation->create($attributes($row, $position));
            }

            $saved->push($model);
        }

        $relation->whereNotIn('id', $saved->pluck('id')->all())->delete();

        return $saved;
    }

    /**
     * Total Modal, transport columns and status all derive from the saved children, so they are
     * recomputed from the database after every create/edit instead of trusting the payload.
     */
    private function refreshDerivedFields(Order $order): void
    {
        $order->load(self::RELATIONS);
        $costed = $this->costedAssignments($order);
        $firstAssignment = $costed->first();

        $order->update([
            'total' => $order->legModalValue()
                + $order->addOns->sum(fn ($a) => $a->qty * $a->modal)
                + $costed->sum('biaya_transport_modal'),
            'vehicle_id' => $firstAssignment?->vehicle_id,
            'supplier_id' => $firstAssignment?->supplier_id,
            'biaya_transport_modal' => $costed->sum('biaya_transport_modal'),
            'status' => $this->resolveStatus($order, $costed)->value,
        ]);
    }

    /** Assignment rows that actually carry a vehicle or a supplier. */
    private function costedAssignments(Order $order): Collection
    {
        return $order->assignments->filter(fn ($a) => $a->vehicle_id || $a->supplier_id)->values();
    }

    /**
     * Tour and Layanan wait on "Menunggu Supplier/Driver" until every assignment has both a vehicle
     * and a supplier, then move to "Siap Kirim WA". Any later status (WA sent, ongoing, done,
     * cancelled) is driven by its own action, so an edit must never move it.
     */
    private function resolveStatus(Order $order, Collection $costed): OrderStatus
    {
        $awaitingTransport = [OrderStatus::MenungguSupplierDriver, OrderStatus::SiapKirimWa];
        $hasTransportStep = in_array($order->tipe, [OrderType::Tour, OrderType::Layanan], true);

        if (! $hasTransportStep || ! in_array($order->status, $awaitingTransport, true)) {
            return $order->status;
        }

        $transportComplete = $costed->isNotEmpty() && $costed->every(fn ($a) => $a->vehicle_id && $a->supplier_id);

        return $transportComplete ? OrderStatus::SiapKirimWa : OrderStatus::MenungguSupplierDriver;
    }

    /** Which vehicle/supplier pairs the Order's assignments hold — compared before and after an edit. */
    private function assignmentSignature(Order $order): string
    {
        return $order->assignments()->get()
            ->filter(fn ($a) => $a->vehicle_id || $a->supplier_id)
            ->map(fn ($a) => "{$a->vehicle_id}:{$a->supplier_id}")
            ->sort()
            ->implode(',');
    }

    private function assignedSupplierNames(Order $order): string
    {
        return $order->assignments->pluck('supplier.nama')->filter()->unique()->implode(', ');
    }

    /**
     * Appends rows to an Order's or Invoice's Riwayat.
     *
     * @param  list<array{kind: string, detail?: string|null}>  $entries
     */
    private function logEvents(HasMany $events, User $actor, array $entries): void
    {
        $events->createMany(array_map(
            fn ($entry) => [...$entry, 'actor_id' => $actor->id, 'at' => now()],
            $entries,
        ));
    }

    /** One Order → one Invoice: created with the Order, then kept in step with every edit. */
    private function syncInvoice(Order $order, User $actor, string $supplierNames): Invoice
    {
        $invoice = $order->invoices()->with('lines')->first() ?? $this->createInvoice($order, $actor, $supplierNames);

        $this->syncInvoiceLines($invoice, $this->invoiceLines($order));

        return $invoice;
    }

    private function createInvoice(Order $order, User $actor, string $supplierNames): Invoice
    {
        $invoice = $order->invoices()->create([
            'nomor' => $this->numbering->nextInvoiceNomor($order),
            'client_id' => $order->client_id,
            'total' => 0,
            'sisa' => 0,
            'tanggal_dibuat' => now()->toDateString(),
            'status' => 'Belum Ditagihkan',
            'catatan' => 'Dibuat otomatis dari Order Review & Konfirmasi — isi Harga Jual sebelum download Excel.',
        ]);

        $events = [['kind' => 'created']];
        if ($supplierNames) {
            $events[] = ['kind' => 'assigned', 'detail' => $supplierNames];
        }
        $this->logEvents($invoice->events(), $actor, $events);

        return $invoice;
    }

    /**
     * Lines are matched by description: an existing line only gets its Modal, supplier and qty
     * refreshed (its Harga Jual stays) and missing lines are added. Only stale Transport lines are
     * removed — every other line may have been added or renamed by hand on the Invoice.
     */
    private function syncInvoiceLines(Invoice $invoice, array $wantedLines): void
    {
        $unclaimed = $invoice->lines->values();

        foreach ($wantedLines as $line) {
            $match = $this->claimLine($unclaimed, $line['deskripsi']);

            if ($match) {
                $match->update(Arr::except($line, 'harga_jual'));
            } else {
                $invoice->lines()->create($line);
            }
        }

        $unclaimed->filter(fn ($line) => str_starts_with($line->deskripsi, 'Transport'))->each->delete();
    }

    /**
     * Takes the existing line for $deskripsi out of $unclaimed. The Layanan main line carries the
     * guest's name ("Transfer — Mr. John"), so after a rename it is found by its label instead of
     * being left behind next to a new line.
     */
    private function claimLine(Collection $unclaimed, string $deskripsi): ?InvoiceLine
    {
        $index = $unclaimed->search(fn ($line) => $line->deskripsi === $deskripsi);

        if ($index === false) {
            $label = Arr::first(self::LAYANAN_LABELS, fn ($label) => str_starts_with($deskripsi, "{$label} — "));
            $index = $label ? $unclaimed->search(fn ($line) => str_starts_with($line->deskripsi, "{$label} — ")) : false;
        }

        return $index === false ? null : $unclaimed->pull($index);
    }

    /** Mirrors buildOrderLines() + the Add-On/Transport line builders in OrderReview.tsx. */
    private function invoiceLines(Order $order): array
    {
        $mainLines = match ($order->tipe) {
            OrderType::Ticket => $order->ticketRows->map(fn ($r) => [
                'deskripsi' => $r->nama_tiket,
                'qty' => $r->qty,
                'modal' => $r->modal_satuan,
                'harga_jual' => 0,
                'supplier_nama' => $r->supplier_nama,
            ])->all(),
            OrderType::Tour => $this->tourActivityLines($order),
            OrderType::Layanan => [[
                'deskripsi' => $this->mainLineLabel($order),
                'qty' => 1,
                'modal' => $order->layananDetail?->biaya_modal ?? 0,
                'harga_jual' => 0,
                'supplier_nama' => $order->layananDetail?->supplier_nama,
            ]],
        };

        $addOnLines = $order->addOns->map(fn ($a) => [
            'deskripsi' => 'Add-On: '.($a->product?->nama ?? 'Produk'),
            'qty' => $a->qty,
            'modal' => $a->modal,
            'harga_jual' => 0,
            'supplier_nama' => $a->supplier_nama,
            'skip_biaya' => $a->skip_biaya,
        ])->all();

        return [...$mainLines, ...$addOnLines, ...$this->transportInvoiceLines($order)];
    }

    private function mainLineLabel(Order $order): string
    {
        if ($order->tipe === OrderType::Tour) {
            return "Paket Tur — {$order->nama_order}";
        }

        $label = self::LAYANAN_LABELS[$order->sub_tipe?->value] ?? self::LAYANAN_LABELS['default'];

        return "{$label} — ".($order->layananDetail?->nama_tamu ?: '—');
    }

    /** One line per itinerary activity — never one lumped "Paket Tur" line, so Modal/Supplier Tur/Harga Jual all stay traceable. */
    private function tourActivityLines(Order $order): array
    {
        $multiDay = $order->itineraryDays->count() > 1;
        $lines = [];

        foreach ($order->itineraryDays as $day) {
            foreach ($day->activities as $act) {
                $lines[] = [
                    'deskripsi' => $multiDay ? "Hari {$day->hari} — {$act->aktivitas}" : ($act->aktivitas ?: 'Aktivitas'),
                    'qty' => 1,
                    'modal' => $act->biaya,
                    'harga_jual' => 0,
                    'supplier_nama' => $act->supplier_tur,
                    'skip_biaya' => $act->skip_biaya,
                ];
            }
        }

        return $lines;
    }

    /** One line per costed vehicle assignment, "#N" suffix only when a day carries more than one. */
    public function transportInvoiceLines(Order $order): array
    {
        $costed = $order->assignments->filter(fn ($a) => $a->biaya_transport_modal > 0);
        $multiDay = $order->assignmentDayCount() > 1;
        $lines = [];

        foreach ($costed->groupBy('hari') as $hari => $rowsForDay) {
            foreach ($rowsForDay->values() as $idx => $a) {
                $label = $multiDay ? "Transport Hari {$hari}" : 'Transport';
                $suffix = $rowsForDay->count() > 1 ? ' #'.($idx + 1) : '';
                $vehicleName = $a->vehicle?->nama ?? Vehicle::find($a->vehicle_id)?->nama ?? 'Mobil';
                $supplierName = $a->supplier?->nama ?? Supplier::find($a->supplier_id)?->nama;

                $lines[] = [
                    'deskripsi' => "{$label}{$suffix} — {$vehicleName} + Driver",
                    'qty' => 1,
                    'modal' => $a->biaya_transport_modal,
                    'harga_jual' => 0,
                    'supplier_nama' => $supplierName,
                ];
            }
        }

        return $lines;
    }
}
