<?php

namespace App\Http\Controllers;

use Excel;
use App\Enums\OrderType;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\PayablesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf;

use App\Exports\AccountPayableExport;

/**
 * Membuat PDF di balik tombol "Unduh PDF": itinerary order, invoice, dan laporan keuangan.
 * Semua route wajib login. HTML dari view `resources/views/exports/*` dirender menjadi PDF oleh
 * Chrome headless (Browsershot), sehingga server butuh Node.js, paket npm puppeteer, dan Chrome/Chromium
 * (lihat LARAVEL_PDF_* di .env.example). Data perusahaan di kop diambil dari config/company.php.
 */
class ExportController extends Controller
{
    public function __construct(private readonly PayablesService $payables) {}

    /** An Order's client-facing itinerary — no Modal or Biaya — in the layout of its type. */
    public function order_pdf(Request $request)
    {
        $data = $request->validate(['id' => ['required', 'integer', 'exists:orders,id']]);

        $order = Order::with(['client', 'itineraryDays.activities', 'layananDetail', 'assignments.vehicle', 'ticketRows', 'guests'])
            ->findOrFail($data['id']);

        $view = match ($order->tipe) {
            OrderType::Tour => 'exports.itinerary_tour',
            OrderType::Layanan => 'exports.itinerary_service',
            OrderType::Ticket => 'exports.itinerary_ticket',
        };

        return $this->makePdf($view, ['order' => $order], "itinerary-{$order->kode}.pdf", 0);
    }

    public function invoice_pdf(Request $request)
    {
        $data = $request->validate(['id' => ['required', 'integer', 'exists:invoices,id']]);

        $invoice = Invoice::with(['order', 'client', 'lines'])->findOrFail($data['id']);

        return $this->makePdf('exports.invoice', ['invoice' => $invoice], "invoice-{$invoice->nomor}.pdf");
    }

    /** Tagihan of one month (?month=YYYY-MM, default this month), the same rows and figures the Tagihan screen shows. */
    public function account_payable_pdf(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));

        $groups = $this->payables->derive()
            ->filter(fn ($g) => ! $month || str_starts_with((string) $g['tanggalOrder'], $month))
            ->values();

        return $this->makePdf('exports.account_payable', [
            'groups' => $groups,
            'summary' => $this->payables->summary($groups),
            'periode' => $this->periodLabel($month),
            'printedBy' => $request->user()->nama,
        ], 'account-payable.pdf');
    }

    /** Invoices (?status=, default "Sudah Ditagihkan"; ?month=YYYY-MM optional) with what is still owed on them. */
    public function account_receivable_pdf(Request $request)
    {
        $status = $request->query('status', 'Sudah Ditagihkan');
        $month = $request->query('month');

        $invoices = Invoice::with(['order', 'client'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($month, fn ($query) => $query->where('tanggal_dibuat', 'like', $month.'%'))
            ->orderBy('nomor')
            ->get();

        return $this->makePdf('exports.account_receivable', [
            'invoices' => $invoices,
            'piutang' => $invoices->where('status.value', 'Sudah Ditagihkan')->where('sisa', '>', 0)->sum('sisa'),
            'periode' => $this->periodLabel($month),
            'printedBy' => $request->user()->nama,
        ], 'account-receivable.pdf');
    }

    /** Revenue minus Modal per month, from the invoices created that month (?from= & ?to= as YYYY-MM, default the last 4 months). */
    public function profit_loss_pdf(Request $request)
    {
        $to = Carbon::createFromFormat('Y-m', $request->query('to', now()->format('Y-m')))->startOfMonth();
        $from = Carbon::createFromFormat('Y-m', $request->query('from', $to->copy()->subMonths(3)->format('Y-m')))->startOfMonth();

        $invoices = Invoice::with(['order', 'lines'])
            ->whereBetween('tanggal_dibuat', [$from->toDateString(), $to->copy()->endOfMonth()->toDateString()])
            ->get()
            ->reject(fn ($invoice) => $invoice->order?->status->value === 'Dibatalkan')
            ->groupBy(fn ($invoice) => $invoice->tanggal_dibuat->format('Y-m'));

        $months = [];
        for ($month = $from->copy(); $month <= $to; $month->addMonth()) {
            $rows = $invoices->get($month->format('Y-m'), collect());
            $pendapatan = $rows->sum(fn ($i) => $i->lines->sum(fn ($l) => $l->qty * $l->harga_jual));
            $modal = $rows->sum(fn ($i) => $i->lines->sum(fn ($l) => $l->qty * $l->modal));

            $months[] = ['label' => $month->locale('id')->translatedFormat('F Y'), 'pendapatan' => $pendapatan, 'modal' => $modal, 'laba' => $pendapatan - $modal];
        }

        return $this->makePdf('exports.profit_loss', [
            'months' => $months,
            'periode' => $from->locale('id')->translatedFormat('M Y').' s/d '.$to->locale('id')->translatedFormat('M Y'),
            'printedBy' => $request->user()->nama,
        ], 'profit-loss.pdf');
    }





		/**
		 * Excel invoice: semua baris yang cocok (tanpa paginasi). Filter: id/ids (invoice tertentu), status, client_id,
		 * month (YYYY-MM), date_from/date_to (tanggal dibuat), q (nomor atau kode order).
		 */
		public function invoice_excel(Request $request)
		{
			$q = trim((string) $request->query('q', ''));
			$ids = collect(explode(',', (string) $request->query('ids', $request->query('id', ''))))->filter()->map(fn ($id) => (int) $id);

			$invoices = Invoice::query()
				->with(['order:id,kode,status', 'client:id,nama', 'lines:id,invoice_id,qty,modal'])
				->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('id', $ids))
				->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
				->when($request->filled('client_id'), fn ($query) => $query->where('client_id', $request->query('client_id')))
				->when($request->filled('month'), fn ($query) => $query->where('tanggal_dibuat', 'like', $request->query('month').'%'))
				->when($request->filled('date_from'), fn ($query) => $query->whereDate('tanggal_dibuat', '>=', $request->query('date_from')))
				->when($request->filled('date_to'), fn ($query) => $query->whereDate('tanggal_dibuat', '<=', $request->query('date_to')))
				->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
					->where('nomor', 'like', "%{$q}%")
					->orWhereHas('order', fn ($o) => $o->where('kode', 'like', "%{$q}%"))))
				->latest('tanggal_dibuat')->latest('id')
				->get();

			return Excel::download(new AccountPayableExport('exports.excel.invoice', ['arr' => $invoices], ['E', 'F', 'G', 'H', 'I']), 'invoice.xlsx');
		}

		/** Excel daftar order: semua baris yang cocok dengan filter yang sama dengan layar Order (tanpa paginasi). */
		public function order_excel(Request $request)
		{
			$q = trim((string) $request->query('q', ''));

			$orders = Order::query()
				->with(['client:id,kode', 'createdBy:id,nama', 'guests:id,order_id,nama'])
				->when($request->filled('tipe'), fn ($query) => $query->where('tipe', $request->query('tipe')))
				->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
				->when($request->filled('supplier_id'), fn ($query) => $query->where(fn ($w) => $w
					->where('supplier_id', $request->query('supplier_id'))
					->orWhereHas('assignments', fn ($a) => $a->where('supplier_id', $request->query('supplier_id')))))
				->when($request->filled('date_from'), fn ($query) => $query->whereDate('tanggal_mulai', '>=', $request->query('date_from')))
				->when($request->filled('date_to'), fn ($query) => $query->whereDate('tanggal_mulai', '<=', $request->query('date_to')))
				->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
					->where('kode', 'like', "%{$q}%")
					->orWhere('kode_group', 'like', "%{$q}%")
					->orWhere('nama_order', 'like', "%{$q}%")
					->orWhere('destinasi', 'like', "%{$q}%")))
				->latest()
				->get();

			return Excel::download(new AccountPayableExport('exports.excel.order', ['arr' => $orders], ['M']), 'order.xlsx');
		}

		/** Tagihan of one month (?month=YYYY-MM, default this month), the same rows and figures the Tagihan screen shows. */
		public function account_payable_excel(Request $request)
		{
				$month = $request->query('month', now()->format('Y-m'));

				$groups = $this->payables->derive()
						->filter(fn ($g) => ! $month || str_starts_with((string) $g['tanggalOrder'], $month))
						->values();

				return Excel::download(new AccountPayableExport('exports.excel.account_payable', [
						'groups' => $groups,
						'summary' => $this->payables->summary($groups),
						'periode' => $this->periodLabel($month),
						'printedBy' => $request->user()->nama,
				], ['E']), 'account-payable.xlsx');
		}

		/** Invoices (?status=, default "Sudah Ditagihkan"; ?month=YYYY-MM optional) with what is still owed on them. */
		public function account_receivable_excel(Request $request)
		{
				$status = $request->query('status', 'Sudah Ditagihkan');
				$month = $request->query('month');

				$invoices = Invoice::with(['order', 'client'])
						->when($status, fn ($query) => $query->where('status', $status))
						->when($month, fn ($query) => $query->where('tanggal_dibuat', 'like', $month.'%'))
						->orderBy('nomor')
						->get();

				return Excel::download(new AccountPayableExport('exports.excel.account_receivable', [
						'invoices' => $invoices,
						'piutang' => $invoices->where('status.value', 'Sudah Ditagihkan')->where('sisa', '>', 0)->sum('sisa'),
						'periode' => $this->periodLabel($month),
						'printedBy' => $request->user()->nama,
				], ['D', 'E']), 'account-receivable.xlsx');
		}

		/** Revenue minus Modal per month, from the invoices created that month (?from= & ?to= as YYYY-MM, default the last 4 months). */
		public function profit_loss_excel(Request $request)
		{
				$to = Carbon::createFromFormat('Y-m', $request->query('to', now()->format('Y-m')))->startOfMonth();
				$from = Carbon::createFromFormat('Y-m', $request->query('from', $to->copy()->subMonths(3)->format('Y-m')))->startOfMonth();

				$invoices = Invoice::with(['order', 'lines'])
						->whereBetween('tanggal_dibuat', [$from->toDateString(), $to->copy()->endOfMonth()->toDateString()])
						->get()
						->reject(fn ($invoice) => $invoice->order?->status->value === 'Dibatalkan')
						->groupBy(fn ($invoice) => $invoice->tanggal_dibuat->format('Y-m'));

				$months = [];
				for ($month = $from->copy(); $month <= $to; $month->addMonth()) {
						$rows = $invoices->get($month->format('Y-m'), collect());
						$pendapatan = $rows->sum(fn ($i) => $i->lines->sum(fn ($l) => $l->qty * $l->harga_jual));
						$modal = $rows->sum(fn ($i) => $i->lines->sum(fn ($l) => $l->qty * $l->modal));

						$months[] = ['label' => $month->locale('id')->translatedFormat('F Y'), 'pendapatan' => $pendapatan, 'modal' => $modal, 'laba' => $pendapatan - $modal];
				}


				return Excel::download(new AccountPayableExport('exports.excel.profit_loss', [
						'months' => $months,
						'periode' => $from->locale('id')->translatedFormat('M Y').' s/d '.$to->locale('id')->translatedFormat('M Y'),
						'printedBy' => $request->user()->nama,
				], ['B', 'C', 'D']), 'profit-loss.xlsx');
		}

    /** Label periode untuk judul laporan, mis. "Oktober 2026"; tanpa bulan menjadi "Semua periode". */
    private function periodLabel(?string $month): string
    {
        return $month ? Carbon::createFromFormat('Y-m', $month)->locale('id')->translatedFormat('F Y') : 'Semua periode';
    }

    /** Rangkai PDF A4 portrait dari view; `--no-sandbox` diperlukan agar Chrome bisa jalan di server/container tanpa root sandbox. */
    private function makePdf(string $view, array $data, string $filename, float $margin = 10)
    {
        return Pdf::view($view, $data)
            ->withBrowsershot(function ($browsershot) {
                $browsershot->noSandbox()
                    ->setEnvironmentOptions(['CHROME_CONFIG_HOME' => storage_path('app/chrome/.config')]);
            })
            ->portrait()
            ->format('a4')
            ->margins($margin, $margin, $margin, $margin)
            ->name($filename);
    }
}
