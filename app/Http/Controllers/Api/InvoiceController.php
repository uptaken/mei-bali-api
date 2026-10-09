<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Endpoint invoice (tagihan ke client). Invoice dibuat dan dihitung oleh OrderService; yang boleh diubah
 * tangan hanyalah Harga Jual per baris. Controller ini juga mengatur status ditagihkan dan mencatat Riwayat.
 */
class InvoiceController extends Controller
{
    /** Relasi yang dikirim bersama satu invoice. */
    private const WITH = ['order', 'client', 'lines', 'events.actor'];

    /**
     * GET /api/invoices — daftar berpaginasi. Filter: status, client_id, month (YYYY-MM), q (nomor atau kode order).
     * Tiap invoice membawa `modal` (jumlah modal semua barisnya) untuk kolom margin di daftar.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return Invoice::query()
            ->with(['order:id,kode,nama_order', 'client:id,nama', 'lines' => function ($query) {
								$query->select('id', 'invoice_id', 'qty', 'modal', 'harga_jual', 'supplier_nama', 'deskripsi', 'skip_biaya');
						}, 'events' => function ($query) {
								$query->select('id', 'invoice_id', 'kind', 'actor_id', 'at', 'detail',);
						},])
						->withSum('lines as modal', 'modal')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('client_id'), fn ($query) => $query->where('client_id', $request->query('client_id')))
            ->when($request->filled('month'), fn ($query) => $query->where('tanggal_dibuat', 'like', $request->query('month').'%'))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('nomor', 'like', "%{$q}%")
                ->orWhereHas('order', fn ($o) => $o->where('kode', 'like', "%{$q}%"))))
            ->latest()
            ->paginate($request->integer('per_page', 20));
    }

    /** GET /api/invoices/{invoice} — satu invoice lengkap dengan order, client, baris, dan riwayat. */
    public function show(Invoice $invoice)
    {
        return $invoice->load(self::WITH);
    }

    /**
     * PATCH /api/invoices/{invoice}/lines — the only thing ever hand-edited on an invoice: Harga Jual
     * (and description/qty for a manually added line). Billing status has its own endpoints.
     */
    public function updateLines(Request $request, Invoice $invoice): Invoice
    {
        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.id' => ['nullable', 'integer', 'exists:invoice_lines,id'],
            'lines.*.deskripsi' => ['nullable', 'string'],
            'lines.*.qty' => ['nullable', 'integer', 'min:1'],
            'lines.*.hargaJual' => ['nullable', 'integer', 'min:0'],
        ]);

        $existing = $invoice->lines()->get()->keyBy('id');
        $seen = [];

        foreach ($data['lines'] as $line) {
            if (! empty($line['id']) && $existing->has($line['id'])) {
                $existing[$line['id']]->update([
                    'deskripsi' => $line['deskripsi'] ?? $existing[$line['id']]->deskripsi,
                    'qty' => $line['qty'] ?? 1,
                    'harga_jual' => $line['hargaJual'] ?? 0,
                ]);
                $seen[] = $line['id'];
            } else {
                $new = $invoice->lines()->create([
                    'deskripsi' => $line['deskripsi'] ?? '',
                    'qty' => $line['qty'] ?? 1,
                    'modal' => 0,
                    'harga_jual' => $line['hargaJual'] ?? 0,
                ]);
                $seen[] = $new->id;
            }
        }

        // Baris yang tidak ikut dikirim dianggap dihapus oleh pengguna.
        $invoice->lines()->whereNotIn('id', $seen)->delete();

        $total = $invoice->lines()->get()->sum(fn ($l) => $l->qty * $l->harga_jual);
        // Yang sudah dibayar client dipertahankan; hanya sisa yang dihitung ulang dari total baru.
        $paid = $invoice->total - $invoice->sisa;
        $invoice->update(['total' => $total, 'sisa' => max(0, $total - $paid)]);

        $invoice->events()->create(['kind' => 'updated', 'actor_id' => $request->user()->id, 'at' => now()]);

        return $invoice->fresh(self::WITH);
    }

    /** Every line needs a Harga Jual > 0 (or skip_biaya) before billing can be marked / Excel generated. */
    private function allPriced(Invoice $invoice): bool
    {
        $lines = $invoice->lines;

        return $lines->isNotEmpty() && $lines->every(fn ($l) => $l->skip_biaya || $l->harga_jual > 0);
    }

    /** POST /api/invoices/{invoice}/mark-billed — tandai Sudah Ditagihkan; ditolak (422) bila masih ada baris tanpa Harga Jual. */
    public function markBilled(Request $request, Invoice $invoice): Invoice|\Illuminate\Http\JsonResponse
    {
        if (! $this->allPriced($invoice)) {
            return response()->json(['message' => 'Isi Harga Jual untuk semua item sebelum ditagihkan.'], 422);
        }

        $invoice->update(['status' => InvoiceStatus::SudahDitagihkan->value, 'tanggal_ditagihkan' => now()->toDateString()]);
        $invoice->events()->create(['kind' => 'billed', 'actor_id' => $request->user()->id, 'at' => now()]);

        return $invoice->fresh(self::WITH);
    }

    /** POST /api/invoices/{invoice}/unmark-billed — batalkan penandaan ditagihkan (kembali Belum Ditagihkan). */
    public function unmarkBilled(Request $request, Invoice $invoice): Invoice
    {
        $invoice->update(['status' => InvoiceStatus::BelumDitagihkan->value, 'tanggal_ditagihkan' => null]);
        $invoice->events()->create(['kind' => 'unbilled', 'actor_id' => $request->user()->id, 'at' => now()]);

        return $invoice->fresh(self::WITH);
    }

    /** POST /api/invoices/{invoice}/excel-downloaded — the Excel itself is built elsewhere; this only writes the Riwayat entry. */
    public function excelDownloaded(Request $request, Invoice $invoice): Invoice
    {
        $invoice->events()->create(['kind' => 'excel', 'actor_id' => $request->user()->id, 'at' => now()]);

        return $invoice->fresh(self::WITH);
    }

    /** POST /api/invoices/{invoice}/payments — records money in, doesn't change status (frontend's own contract). */
    public function recordPayment(Request $request, Invoice $invoice): Invoice
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1']]);

        $newSisa = max(0, $invoice->sisa - $data['amount']);
        $invoice->update(['sisa' => $newSisa]);
        $invoice->events()->create([
            'kind' => 'payment', 'actor_id' => $request->user()->id, 'at' => now(),
            'detail' => 'Rp '.number_format($data['amount'], 0, ',', '.'),
        ]);

        return $invoice->fresh(self::WITH);
    }
}
