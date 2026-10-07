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
use App\Models\Order;
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

class ExportController extends BaseController{
	public function order_pdf(Request $request){
		$data = $request->validate([
			'order_id' => ['required', 'integer', 'exists:orders,id'],
		]);
		$order = Order::with(['client', 'itineraryDays.activities'])
			->findOrFail($data['order_id']);

		return NEWPDF1::view('exports.order_pdf', [
			'order' => $order,
		])
			->withBrowsershot(function ($browsershot) {
				$browsershot->noSandbox()
					->setEnvironmentOptions([
						'CHROME_CONFIG_HOME' => storage_path('app/chrome/.config'),
					]);
			})
			->portrait()
			->format('a4')
			->margins(10, 10, 10, 10)
			->name("order-{$order->kode}.pdf")
			->download();
	}
}
