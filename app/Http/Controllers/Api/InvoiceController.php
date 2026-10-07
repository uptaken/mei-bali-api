<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    private const WITH = ['order', 'client', 'lines', 'events.actor'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return Invoice::query()
            ->with(['order:id,kode,nama_order', 'client:id,nama', 'lines' => function ($query) {
								$query->select('id', 'invoice_id', 'qty', 'modal', 'harga_jual', 'supplier_nama', 'deskripsi');
						}, 'events' => function ($query) {
								$query->select('id', 'invoice_id', 'kind', 'actor_id as actorId', 'at',);
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

    public function show(Invoice $invoice)
    {
        return $invoice->load(self::WITH);
    }

    /** PATCH /api/invoices/{invoice}/lines — the only thing ever hand-edited on an invoice: Harga Jual (and description/qty for a manually added line). */
    public function updateLines(Request $request, Invoice $invoice): Invoice
    {
        $data = $request->validate([
					'status' => ['string', ],
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
                    'deskripsi' => $line['deskripsi'],
                    'qty' => $line['qty'],
                    'harga_jual' => $line['hargaJual'],
                ]);
                $seen[] = $line['id'];
            } else {
                $new = $invoice->lines()->create([
                    'deskripsi' => $line['deskripsi'],
                    'qty' => $line['qty'],
                    'modal' => 0,
                    'harga_jual' => $line['hargaJual'],
                ]);
                $seen[] = $new->id;
            }
        }

        $invoice->lines()->whereNotIn('id', $seen)->delete();

        $total = $invoice->lines()->get()->sum(fn ($l) => $l->qty * $l->harga_jual);
        $paid = $invoice->total - $invoice->sisa;
        $invoice->update(['total' => $total, 'sisa' => max(0, $total - $paid), 'status' => $request->status,]);

        $invoice->events()->create(['kind' => 'updated', 'actor_id' => $request->user()->id, 'at' => now()]);

        return $invoice->fresh(self::WITH);
    }

    /** Every line needs a Harga Jual > 0 (or skip_biaya) before billing can be marked / Excel generated. */
    private function allPriced(Invoice $invoice): bool
    {
        $lines = $invoice->lines;

        return $lines->isNotEmpty() && $lines->every(fn ($l) => $l->skip_biaya || $l->harga_jual > 0);
    }

    public function markBilled(Request $request, Invoice $invoice): Invoice|\Illuminate\Http\JsonResponse
    {
        if (! $this->allPriced($invoice)) {
            return response()->json(['message' => 'Isi Harga Jual untuk semua item sebelum ditagihkan.'], 422);
        }

        $invoice->update(['status' => InvoiceStatus::SudahDitagihkan->value, 'tanggal_ditagihkan' => now()->toDateString()]);
        $invoice->events()->create(['kind' => 'billed', 'actor_id' => $request->user()->id, 'at' => now()]);

        return $invoice->fresh(self::WITH);
    }

    public function unmarkBilled(Request $request, Invoice $invoice): Invoice
    {
        $invoice->update(['status' => InvoiceStatus::BelumDitagihkan->value, 'tanggal_ditagihkan' => null]);
        $invoice->events()->create(['kind' => 'unbilled', 'actor_id' => $request->user()->id, 'at' => now()]);

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
