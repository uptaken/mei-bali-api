<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Auth;
use Excel;
use NEWPDF1;
use Storage;

use App\Http\Controllers\BaseController;

use App\Exports\UserExport;
use App\Exports\RegistrationEventExport;
use App\Exports\GenerateMatchExport;
use App\Exports\PlayerExport;
use App\Exports\PlayerMultiSheetExport;
use App\Exports\CoachExport;
use App\Exports\BestPlayerExport;
use App\Exports\TopScorerExport;
use App\Exports\LadderExport;
use App\Exports\OrderExport;
use App\Exports\OrderTicketExport;
use App\Exports\OrderSponsorshipExport;
use App\Exports\TournamentExport;

use App\Models\Base;
use App\Models\RegistrationEvent;
use App\Models\CategorySport;
use App\Models\EventCategorySport;
use App\Models\EventCategorySportCategory;
use App\Models\Event;
use App\Models\CutoffGroup;
use App\Models\Type;
use App\Models\Info;
use App\Models\Invoice;
use App\Models\Order;
use App\Enums\OrderType;
use App\Models\OrderTicket;
use App\Models\OrderSponsorship;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\RegistrationEventPlayer;
use App\Models\RegistrationEventCoach;
use App\Models\MatchEvent;
use App\Models\MatchModel;
use App\Models\CutoffGroupMember;
use App\Models\PlayerPosition;
use App\Models\CoachPosition;
use App\Models\Tournament;
use App\Models\City;
use App\Models\Certificate;
use App\Models\Province;
use App\Models\Country;
use App\Models\CertificateExportLog;
use App\Models\Venue;
use App\Models\User;

use App\Jobs\SendEmailOrderSponsorshipJob;

use App\Enums\PayableStatus;

class ExportController extends BaseController{
	private const TEMPLATE_EXPORTS = [
		'itinerary_tour' => 'Itinerary-Tour',
		'itinerary_service' => 'Itinerary-Service',
		'itinerary_ticket' => 'Itinerary-Ticket',
		'invoice' => 'Invoice',
		'profit_loss' => 'Profit-Loss',
		'account_receivable' => 'Account-Receivable',
		'account_payable' => 'Account-Payable',
	];

	public function order_pdf(Request $request){
		$data = $request->validate([
			'id' => ['required', 'integer', 'exists:orders,id'],
		]);
		$order = Order::with(['client', 'itineraryDays.activities'])
			->findOrFail($data['id']);

		$resource = $order->tipe === OrderType::Tour
			? 'exports.itinerary_tour'
			: 'exports.order_pdf';

		return $this->makePdf(
			$resource,
			['order' => $order],
			"order-{$order->kode}.pdf",
			false,
			$resource === 'exports.itinerary_tour' ? 0 : 10,
		);
	}

	public function invoice_pdf(Request $request){
		// $data = $request->validate([
		// 	'order_id' => ['required', 'integer', 'exists:orders,id'],
		// ]);
		$invoice = Invoice::with(['order', 'lines', 'events'])
			->findOrFail($request->id);

		// return view('exports.invoice', [
		// 	'invoice' => $invoice,
		// ]);

		return $this->makePdf(
			'exports.invoice',
			['invoice' => $invoice],
			"invoice-{$invoice->nomor}.pdf",
		);
	}

	public function account_payable_pdf(Request $request){
		// $data = $request->validate([
		// 	'order_id' => ['required', 'integer', 'exists:orders,id'],
		// ]);
		$arrOrder = Order::with([
			'client',  'invoices',
			'itineraryDays.activities' => function($q) use($request) {
				// $q->where('bayar_status', 'like', '%'.PayableStatus::BelumBayar->value.'%');
				$q->where('created_at', 'like', ($request->has('date') ? $request->date : Carbon::now()->isoFormat('YYYY-MM')).'%');
			},
			'ticketRows' => function($q) use($request) {
				// $q->where('bayar_status', 'like', '%'.PayableStatus::BelumBayar->value.'%');
				$q->where('created_at', 'like', ($request->has('date') ? $request->date : Carbon::now()->isoFormat('YYYY-MM')).'%');
			},
			'addOns' => function($q) use($request) {
				// $q->where('bayar_status', 'like', '%'.PayableStatus::BelumBayar->value.'%');
				$q->where('created_at', 'like', ($request->has('date') ? $request->date : Carbon::now()->isoFormat('YYYY-MM')).'%');
			},
			'assignments' => function($q) use($request) {
				// $q->where('bayar_status', 'like', '%'.PayableStatus::BelumBayar->value.'%');
				$q->where('created_at', 'like', ($request->has('date') ? $request->date : Carbon::now()->isoFormat('YYYY-MM')).'%');
			},
		])
			->when($request->filled('date'), fn ($query) => $query->where('created_at', 'like', $request->date.'%'))
			->when(!$request->filled('date'), fn ($query) => $query->where('created_at', 'like', Carbon::now()->isoFormat('YYYY-MM').'%'))
			->get();

		// $arrOrder1 = $arrOrder->get();

		$belumDibayar = 0;
		$sudahDibayar = 0;
		$totalSupplier = 0;
		$lewatSeminggu = 0;
		foreach($arrOrder as $order){

			$totalSupplier += $order->combined_supplier['total'];

			if($order->combined_bayar_status == PayableStatus::BelumBayar)
				$belumDibayar++;
			else if($order->combined_bayar_status == PayableStatus::Bayar)
				$sudahDibayar++;

			if(Carbon::parse($order->tanggal_mulai) < Carbon::now()->subDays(7))
				$lewatSeminggu++;
		}

		// return view('exports.invoice', [
		// 	'invoice' => $invoice,
		// ]);

		return $this->makePdf(
			'exports.account_payable',
			[
				'arrOrder' => $arrOrder,
				'belumDibayar' => $belumDibayar,
				'sudahDibayar' => $sudahDibayar,
				'totalSupplier' => $totalSupplier,
				'lewatSeminggu' => $lewatSeminggu,
			],
			"account-payable.pdf",
		);
	}

	public function template_pdf(Request $request, string $template)
	{
		abort_unless(isset(self::TEMPLATE_EXPORTS[$template]), 404);

		$data = [];
		if ($template === 'itinerary_tour') {
			$validated = $request->validate([
				'id' => ['required', 'integer', 'exists:orders,id'],
			]);
			$data['order'] = Order::with(['client', 'itineraryDays.activities'])
				->findOrFail($validated['id']);
		}

		return $this->makePdf(
			"exports.{$template}",
			$data,
			'mei-bali-'.self::TEMPLATE_EXPORTS[$template].'.pdf',
			false,
			0,
		);
	}

	private function makePdf(string $view, array $data, string $filename, bool $download = false, float $margin = 10)
	{
		$pdf = NEWPDF1::view($view, $data)
			->withBrowsershot(function ($browsershot) {
				$browsershot->noSandbox()
					->setEnvironmentOptions([
						'CHROME_CONFIG_HOME' => storage_path('app/chrome/.config'),
					]);
			})
			->portrait()
			->format('a4')
			->margins($margin, $margin, $margin, $margin)
			->name($filename);

		return $download ? $pdf->download() : $pdf;
	}
}
