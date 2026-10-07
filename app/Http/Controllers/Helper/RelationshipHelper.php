<?php
namespace App\Http\Controllers\Helper;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Auth;
use Crypt;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Event\EventCategorySportCategoryController;
use App\Http\Controllers\Helper\Controller\ClassHelper;
use App\Http\Controllers\Helper\RelationshipHelper2;
use App\Http\Controllers\Helper\CacheHelper;
use App\Http\Controllers\Helper\Controller\BaseHelper;

use App\Models\MatchModel;
use App\Models\MatchAttendance;
use App\Models\Outlet;
use App\Models\RegistrationEvent;
use App\Models\RegistrationEventPlayer;
use App\Models\RegistrationEventCoach;
use App\Models\EventCategorySportCategory;
use App\Models\EventCategorySportVenue;
use App\Models\EventCategorySportCoordinator;
use App\Models\EventCategorySport;
use App\Models\Type;
use App\Models\User;
use App\Models\BranchImageView;
use App\Models\EventClick;
use App\Models\EventImage;
use App\Models\EventTableLayout;
use App\Models\Reservation;
use App\Models\OutletImageView;
use App\Models\Setting;
use App\Models\MemberBranch;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderTicket;
use App\Models\OrderTicketDetail;
use App\Models\MatchEvent;
use App\Models\GroupMember;
use App\Models\Tournament;
use App\Models\ScoringTypeCategorySport;
use App\Models\CutoffCategory;
use App\Models\CutoffGroup;
use App\Models\CutoffGroupMember;
use App\Models\Certificate;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Models\Quiz;
use App\Models\TicketCategory;

class RelationshipHelper{
	public $date_format = 'DD/MM/YY HH:mm';
	public $date_only_format = 'DD/MM/YY';
	public $date_data_format = 'YYYY-MM-DD HH:mm:ss';
	public $max_player_team_name = 2;
	public $request_simple_str = 'simple';
	public $max_wrap_text = 15;
	public $image_url = "/media";

	public function get_image($data, $image_url, $column_file_name = 'file_name'){
		// $url = url($this->image_url.$image_url).'?'.$column_file_name.'='.$data->{$column_file_name};
		$arrTemp = [];
		$arrTemp[$column_file_name] = $data->{$column_file_name};

		$url = url($this->image_url.$image_url).'?'.env('REQUEST_PARAM_NAME', 'encrypt').'='.Crypt::encryptString(json_encode($arrTemp));

		$data->image_format = '<img src="'.$url.'" style="width: 6rem"/>';
		$data->url_image = $url;
	}

	public function wrap_text($data, $type = 'new_line'){
		if($type == 'truncate_start'){
			if(strlen($data) < $this->max_wrap_text)
				$str = substr($data, strlen($data));
			else{
				$exp = explode('_', $data);
				$str = '...'.$exp[count($exp) - 2].'_'.$exp[count($exp) - 1];
				// $str = '...'.substr($data, strlen($data) - $this->max_wrap_text);
			}
		}
		else if($type == 'truncate_end' || $type == 'new_line'){
			if(strlen($data) / 2 < $this->max_wrap_text)
				$str = substr($data, 0, strlen($data) / 2).($type == 'truncate_end' ? '...' : '<br/>'.substr($data, strlen($data) / 2));
			else
				$str = substr($data, 0, $this->max_wrap_text).($type == 'truncate_end' ? '...' : '<br/>'.$this->wrap_text(substr($data, $this->max_wrap_text), $type));
		}
		return $str;
	}

	public function get_badge($data, $column_file_name, $text, $color_class, $arr_link = [], $column = ''){
		$badge = '<span class="badge badge-'.$color_class.'">'.$text.'</span>';
		$str = '<p class="m-0 d-flex" style="font-size: 1.2rem;">'.$badge.'</p>';
		foreach($arr_link as $link){
			if(str_contains($text, $link['name'])){
				$str = '<a href="'.$link['link'].'"><p class="m-0 d-flex" style="font-size: 1.2rem;">'.$badge.'</p></a>';
				break;
			}
		}
		$data->{$column_file_name} = $str;
		$data->{$column.'_badge'} = $badge;
		$data->{$column.'_text'} = $text;
	}

	public function user($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);

		$data->type;
		$data->category_sport;
		$data->category_sport_name = !empty($data->category_sport) ? $data->category_sport->name : '-';
		if(!empty($data->city))
			$data->city->province->country;
		if(!empty($data->country))
			$data->country;

		$data->is_player_exist = $data->total_player > 0;
		$data->is_coach_exist = $data->total_coach > 0;
		$data->is_player_not_exist = $data->total_player == 0;
		$data->is_coach_not_exist = $data->total_coach == 0;

		if($data->type->name == 'admin'){
			$data->allow_delete = $data->email != 'admin@admin.com' && (!Auth::check() || (Auth::check() && Auth::user()->id != $data->id));
		}

		if(!empty($request->event_category_sport_id)){
			$event_category_sport_coordinator = $cache_helper->get_key(null, null, $request1, 'user_event_category_sport_coordinator_'.$data->id, 3600, [ 'user', $data->id, ]);
			if(!isset($event_category_sport_coordinator)){
				$event_category_sport_coordinator = EventCategorySportCoordinator::where('event_category_sport_id', '=', $request->event_category_sport_id)
					->where('coordinator_id', '=', $data->id)
					->first();

				$event_category_sport_coordinator = $cache_helper->get_key($event_category_sport_coordinator, null, $request1, 'user_event_category_sport_coordinator_'.$data->id, 3600, [ 'user', $data->id, ]);
			}

			$data->is_coordinator = $data->type->name == 'coordinator' && !empty($event_category_sport_coordinator);
		}
	}

	public function match_log($data, $request = null){
		$data->reset_at_format = $data->reset_at->isoFormat('DD MMMM YYYY HH:mm:ss');
		$data->reset_user_name = $data->reset_user->name;


		if($data->type == 'start_generate_category_sport')
			$this->get_badge($data, 'type_format', 'Start Generate Category Sport', 'primary', [], 'type');
		else if($data->type == 'reset_category_sport')
			$this->get_badge($data, 'type_format', 'Reset Match Category Sport', 'primary', [], 'type');
		else if($data->type == 'finish_generate_category_sport')
			$this->get_badge($data, 'type_format', 'Finish Generate Category Sport', 'primary', [], 'type');
		else if($data->type == 'change_venue')
			$this->get_badge($data, 'type_format', 'Change Venue', 'primary', [], 'type');
		else if($data->type == 'set_finish')
			$this->get_badge($data, 'type_format', 'Set Match Finish', 'primary', [], 'type');
		else if($data->type == 'reset_match')
			$this->get_badge($data, 'type_format', 'Reset Match', 'primary', [], 'type');
		else if($data->type == 'add_score')
			$this->get_badge($data, 'type_format', 'Add Score', 'primary', [], 'type');
		else if($data->type == 'edit_score')
			$this->get_badge($data, 'type_format', 'Edit Score', 'warning', [], 'type');
		else if($data->type == 'delete_score')
			$this->get_badge($data, 'type_format', 'Delete Score', 'danger', [], 'type');
		else if($data->type == 'add_attendance')
			$this->get_badge($data, 'type_format', 'Add Attendance', 'primary', [], 'type');
		else if($data->type == 'edit_attendance')
			$this->get_badge($data, 'type_format', 'Edit Attendance', 'warning', [], 'type');
		else if($data->type == 'delete_attendance')
			$this->get_badge($data, 'type_format', 'Delete Attendance', 'danger', [], 'type');
		else if($data->type == 'commit_certificate')
			$this->get_badge($data, 'type_format', 'Commit Certificate', 'primary', [], 'type');
		else if(empty($data->type) && !empty($data->match))
			$this->get_badge($data, 'type_format', 'Reset Match', 'primary', [], 'type');
		else if(empty($data->type) && empty($data->match))
			$this->get_badge($data, 'type_format', 'Reset Match Category Sport', 'primary', [], 'type');

		if(!empty($data->match_event_id) && empty($data->match_event))
			$match_event = MatchEvent::withTrashed()->find($data->match_event_id);
		else if(!empty($data->match_event_id) && !empty($data->match_event))
			$match_event = $data->match_event;

		if(!empty($data->match_attendance_id) && empty($data->match_attendance))
			$match_attendance = MatchAttendance::withTrashed()->find($data->match_attendance_id);
		else if(!empty($data->match_attendance_id) && !empty($data->match_attendance))
			$match_attendance = $data->match_attendance;

		if($data->type == 'change_venue')
			$data->description = $data->type_text.' from '.$data->from_venue->name.' to '.$data->to_venue->name.
				' on Category Sport '.$data->event_category_sport->category_sport->name;
		else
			$data->description = $data->type_text . ' on ' .
				(!empty($data->match) ? (!empty($data->match->cutoff_group) ? 'Cutoff' : (!empty($data->match->tournament) ? 'Tournament' : 'Group')) : '') . "<br/>" .
				(!empty($data->event_category_sport_category) ? 'Category ' . $data->event_category_sport_category->name : 'Category Sport ' . $data->event_category_sport->category_sport->name) . "<br/>" .
				(!empty($match_event) && !empty($match_event->registration_event) ? ' (Player : '.$match_event->registration_event->player[0]->name.')' : '') .
				(!empty($match_attendance) && !empty($match_attendance->registration_event) ? ' (Player : '.$match_attendance->registration_event->player[0]->name.')' : '');

	}

	public function order_ticket($data, $request = null){
		$data->total_price_format = "Rp. ".number_format($data->total_price, 0, ',', '.');
		$data->created_at_format = $data->created_at->isoFormat($this->date_format);
		$data->status_format1 = __('general.'.$data->status);
		$this->get_badge($data, 'status_format', __('general.'.$data->status), $data->status == 'success' || $data->status == 'redeemed' ? 'primary' : ($data->status == 'wait_payment' ? 'warning text-white' : 'danger'), [], 'status');

		$data->event;
		$data->payment_method;
		if(!empty($data->payment_method)){
			$data->payment_method_name = $data->payment_method->name;
			$this->get_image($data->payment_method, '/payment-method');
		}
		$total_ticket = 0;
		foreach($data->detail as $detail)
			$total_ticket += $detail['amount'];
		$data->total_ticket = $total_ticket;
	}

	public function order_ticket_detail($data, $request = null){
		$data->created_at_format = $data->created_at->isoFormat($this->date_format);
		$data->status = !empty($data->redeemed_at) ? 'redeemed' : $data->order->status;
		$data->status_format1 = __('general.'.$data->status);
		$this->get_badge($data, 'status_format', __('general.'.$data->status), $data->status == 'success' || $data->status == 'redeemed' ? 'primary' : ($data->status == 'wait_payment' ? 'warning text-white' : 'danger'), [], 'status');
	}

	public function order_sponsorship($data, $request = null){
		$data->total_price_format = "Rp. ".number_format($data->total_price, 0, ',', '.');
		$data->created_at_format = $data->created_at->isoFormat($this->date_format);
		$data->status_format1 = __('general.'.$data->status);
		$this->get_badge($data, 'status_format', __('general.'.$data->status), $data->status == 'success' || $data->status == 'redeemed' ? 'primary' : ($data->status == 'wait_payment' ? 'warning text-white' : 'danger'), [], 'status');
		$this->get_badge($data, 'type_format', __('general.'.$data->type), 'primary', [], 'status');
		$data->id_format = $this->wrap_text($data->id);

		$data->payment_method;
		if(!empty($data->payment_method)){
			$data->payment_method_name = $data->payment_method->name;
			$this->get_image($data->payment_method, '/payment-method');
		}
		$total_sponsorship = 0;
		foreach($data->detail as $detail){
			$total_sponsorship += $detail['amount'];
			$detail->sponsorship_package;
		}
		$data->total_sponsorship = $total_sponsorship;

		$event_str = '';
		foreach($data->event as $key => $event){
			if(!empty($event->event))
				$event_str .= ($key > 0 ? ', ' : '').$event->event->name;
		}
		$data->event_str = $event_str;
	}

	public function order_sponsorship_detail($data, $request = null){
		$data->created_at_format = $data->created_at->isoFormat($this->date_format);
		$data->status = !empty($data->redeemed_at) ? 'redeemed' : $data->order->status;
		$data->status_format1 = __('general.'.$data->status);
		$this->get_badge($data, 'status_format', __('general.'.$data->status), $data->status == 'success' || $data->status == 'redeemed' ? 'primary' : ($data->status == 'wait_payment' ? 'warning text-white' : 'danger'), [], 'status');
	}

	public function sponsorship_package($data, $request = null){
		$data->created_at_format = $data->created_at->isoFormat($this->date_format);
		$data->price_format = "Rp. ".number_format($data->price, 0, ',', '.');
		$data->type_format = __('general.'.$data->type);
		$this->get_image($data, '/sponsorship-package');
	}

	public function ticket_category($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);

		$order_ticket_detail = new OrderTicketDetail();
		$order_ticket = new OrderTicket();

		$this->simple_rel($data);

		$total_ticket = $cache_helper->get_key(null, null, $request1, 'ticket_category_total_ticket_'.$data->id, 3600, [ 'total_ticket', $data->id, ]);
		if(!isset($total_ticket)){
			$arr_detail = OrderTicketDetail::select($order_ticket_detail->get_table_name().'.*')
				->join($order_ticket->get_table_name(), $order_ticket_detail->get_table_name().'.order_ticket_id', '=', $order_ticket->get_table_name().'.id')
				->where($order_ticket_detail->get_table_name().'.ticket_category_id', '=', $data->id)
				->where($order_ticket->get_table_name().'.status', '=', 'success')
				->whereNull($order_ticket->get_table_name().'.deleted_at')
				->get();

			$total_ticket = 0;
			foreach($arr_detail as $detail)
				$total_ticket += $detail->amount;

			$total_ticket = $cache_helper->get_key($total_ticket, null, $request1, 'ticket_category_total_ticket_'.$data->id, 3600, [ 'total_ticket', $data->id, ]);
		}
		$data->remain_qty = $data->qty - $total_ticket;
		$data->sold_qty = $total_ticket;
	}

	public function event($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);

		$data->status_publish_format = $data->is_publish == 1 ? 'Published' : 'Not Published';
		$data->status_testing_format = $data->is_testing == 1 ? 'Yes' : 'No';
		$data->type_format = __('general.'.$data->type);
		$data->ticket_category;

		if(count($data->ticket_category) > 0){
			$data->start_from_ticket_category = TicketCategory::where('event_id', '=', $data->id)->orderBy('price', 'asc')->first();

			foreach($data->ticket_category as $ticket_category)
				$this->ticket_category($ticket_category, $request);
		}

		if(!empty($data->file_name)){
			$data->image_format = '<img src="'.(!empty($data->file_name) ? url('/media/event?file_name='.$data->file_name) : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
			$data->url_image = !empty($data->file_name) ? url('/media/event?file_name='.$data->file_name) : url('/image/no_image_available.jpeg');
		}

		if(!empty($data->certificate_file_name)){
			$data->certificate_image_format = '<img src="'.(!empty($data->certificate_file_name) ? url('/media/event/certificate?file_name='.$data->certificate_file_name) : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
			$data->url_certificate_image = !empty($data->certificate_file_name) ? url('/media/event/certificate?file_name='.$data->certificate_file_name) : url('/image/no_image_available.jpeg');
		}

		if(!empty($data->name_tag_file_name)){
			$data->name_tag_image_format = '<img src="'.(!empty($data->name_tag_file_name) ? url('/media/event/name-tag?file_name='.$data->name_tag_file_name) : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
			$data->url_name_tag_image = !empty($data->name_tag_file_name) ? url('/media/event/name-tag?file_name='.$data->name_tag_file_name) : url('/image/no_image_available.jpeg');
		}

		$allow_delete = $cache_helper->get_key(null, null, $request1, 'event_allow_delete_'.$data->id, 3600, [ 'event', 'allow_delete', $data->id, ]);
		if(!isset($allow_delete)){
			$allow_delete = true;
			foreach($data->category_sport as $category_sport){
				if(empty($request) || (!empty($request) && $request->rel_type != $this->request_simple_str)){
					$category_sport->is_match_generated = count($category_sport->group) > 0 || count($category_sport->cutoff_group) > 0 || count($category_sport->tournament) > 0;

					if(!empty($category_sport->category_sport))
						foreach($category_sport->category_sport->scoring_type as $scoring_type)
							$scoring_type->scoring_type;
					$category_sport->coordinator;

					foreach($category_sport->venue as $venue)
						$venue->venue;
				}

				if($allow_delete && ($category_sport->is_certificate_open == 1 || $category_sport->is_match_generated))
					$allow_delete = false;
			}

			$allow_delete = $cache_helper->get_key($allow_delete, null, $request1, 'event_allow_delete_'.$data->id, 3600, [ 'event', 'allow_delete', $data->id, ]);
		}
		$data->allow_delete = $allow_delete;
	}

	public function tournament($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);



		$data->event_category_sport_category->event_category_sport->event;
		$data->event_category_sport_category->event_category_sport->category_sport;
		$data->event_category_sport_category->event_category_sport->scoring_type;
		$data->type;
		$data->venue;
		$data->event_category_sport->scoring_type;
		$data->event_category_sport->category_sport;
		$data->event_category_sport->event;
		$data->event;
		$data->from_tournament1;
		$data->from_tournament2;


		if(!empty($data->registration_event1)){
			$registration_event1 = $cache_helper->get_key(null, null, $request1, 'registration_event_'.$data->registration_event1->id, 3600, [ 'registration', $data->registration_event1->id, ]);
			if(!isset($registration_event1)){
				$this->registration_event($data->registration_event1, $request);

				$registration_event1 = $cache_helper->get_key($data->registration_event1, null, $request1, 'registration_event_'.$data->registration_event1->id, 3600, [ 'registration', $data->registration_event1->id, ]);
			}
			$data->registration_event1 = $registration_event1;
		}



		if(!empty($data->registration_event2)){
			$registration_event2 = $cache_helper->get_key(null, null, $request1, 'registration_event_'.$data->registration_event2->id, 3600, [ 'registration', $data->registration_event2->id, ]);
			if(!isset($registration_event2)){
				$this->registration_event($data->registration_event2, $request);

				$registration_event2 = $cache_helper->get_key($data->registration_event2, null, $request1, 'registration_event_'.$data->registration_event2->id, 3600, [ 'registration', $data->registration_event2->id, ]);
			}
			$data->registration_event2 = $registration_event2;
		}


		foreach($data->event_category_sport->coordinator as $coordinator){
			if(!empty($coordinator->coordinator))
				$coordinator->coordinator->type;
		}
		// if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str)){


		if(count($data->match) > 0){
			$arr_match = $cache_helper->get_key(null, null, $request1, 'match_'.$data->match[0]->id, 3600, [ 'match', $data->match[0]->id, ]);
			if(!isset($arr_match)){
				if(!empty($request))
					$request->merge(["with_tournament" => false]);

				$this->match($data->match[0], $request);

				$arr_match = $cache_helper->get_key($data->match, null, $request1, 'match_'.$data->match[0]->id, 3600, [ 'match', $data->match[0]->id, ]);
			}
			$data->match = $arr_match;
		}
		// }
	}

	public function category_sport($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);


		foreach($data->scoring_type as $scoring_type){
			$scoring_type->scoring_type;
		}
		$data->status_publish_format = $data->is_publish == 1 ? 'Published' : 'Not Published';
		$data->event_category_sport;
		$data->has_event_category_sport = count($data->event_category_sport) == 0;

		if(!empty($request) && !empty($request->event_rel_id)){
			$event_category_sport = $cache_helper->get_key(null, null, $request1, 'category_sport_event_rel_'.$data->id.'_'.$request->event_rel_id, 3600, [ 'event_category_sport', $request->event_rel_id, $data->id, ]);
			// if(!isset($event_category_sport)){
				$event_category_sport = EventCategorySport::where('event_id', '=', $request->event_rel_id)
					->where('category_sport_id', '=', $data->id)
					->first();
				if(!empty($event_category_sport))
					$this->event_category_sport($event_category_sport, $request);

				$event_category_sport = $cache_helper->get_key($event_category_sport, null, $request1, 'category_sport_event_rel_'.$data->id.'_'.$request->event_rel_id, 3600, [ 'event_category_sport', $request->event_rel_id, $data->id, ]);
			// }
			$data->event_category_sport_data = $event_category_sport;
		}

		$this->simple_rel($data);
	}

	public function group($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);


		$data->match;
		$data->event_category_sport_category->event_category_sport->category_sport;
		$data->event_category_sport;
		$data->event;
		$data->type;
		$data->venue;

		$data->best_player_id_format = strlen($data->best_player_id) > 10 ? substr_replace($data->best_player_id, '<br/>', strlen($data->best_player_id) / 2, 0) : $data->best_player_id;
		$data->top_scorer_id_format = strlen($data->top_scorer_id) > 10 ? substr_replace($data->top_scorer_id, '<br/>', strlen($data->top_scorer_id) / 2, 0) : $data->top_scorer_id;

		if(!empty($data->best_player))
			$this->registration_event_player($data->best_player, $request);
		if(!empty($data->top_scorer))
			$this->registration_event_player($data->top_scorer, $request);



		$arr_member = $cache_helper->get_key(null, null, $request1, 'group_member_'.$data->id, 3600, [ 'group', 'member', $data->id, ]);
		if(!isset($arr_member)){
			$arr_member = GroupMember::where('group_id', '=', $data->id)
				->orderBy('point', 'desc')
				->orderBy('goal_difference', 'desc')
				->get();
			foreach($arr_member as $key => $member){

				$this->group_member($member, $request);
			}

			$arr_member = $cache_helper->get_key($arr_member, null, $request1, 'group_member_'.$data->id, 3600, [ 'group', 'member', $data->id, ]);
		}

		$data->member = $arr_member;
	}

	public function cutoff_group($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);


		$cutoff_group_model = new CutoffGroup();
		$cutoff_group_member_model = new CutoffGroupMember();
		$cutoff_category_model = new CutoffCategory();
		$match_model = new MatchModel();
		$match_event_model = new MatchEvent();

		$data->match;
		$data->event_category_sport_category->event_category_sport->category_sport;
		$data->event_category_sport_category->event_category_sport->scoring_type;
		$data->event_category_sport;
		$data->event;
		$data->type;
		$data->venue;
		$data->cutoff_category;

		$data->best_player_id_format = strlen($data->best_player_id) > 10 ? substr_replace($data->best_player_id, '<br/>', strlen($data->best_player_id) / 2, 0) : $data->best_player_id;
		$data->top_scorer_id_format = strlen($data->top_scorer_id) > 10 ? substr_replace($data->top_scorer_id, '<br/>', strlen($data->top_scorer_id) / 2, 0) : $data->top_scorer_id;

		if(!empty($data->best_player)){
			if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type == $this->request_simple_str))
				$this->simple_registration_event($data->best_player, $request);
			else
				$this->registration_event($data->best_player, $request);
		}
		if(!empty($data->top_scorer)){
			if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type == $this->request_simple_str))
				$this->simple_registration_event($data->top_scorer, $request);
			else
				$this->registration_event($data->top_scorer, $request);
		}





		$arr_member = $cache_helper->get_key(null, null, $request1, 'cutoff_group_member_'.$data->id, 3600, [ 'cutoff_group', 'member', $data->id, ]);
		if(!isset($arr_member)){
			$last_cutoff_group = CutoffGroup::select($cutoff_group_model->get_table_name().'.*', $cutoff_category_model->get_table_name().'.level as level')
				->join($cutoff_category_model->get_table_name(), $cutoff_group_model->get_table_name().'.cutoff_category_id', '=', $cutoff_category_model->get_table_name().'.id')
				->join($match_model->get_table_name(), function($join) use($match_model, $cutoff_group_model) {
					$join = $join->on($match_model->get_table_name().'.cutoff_group_id', '=', $cutoff_group_model->get_table_name().'.id')
						->where($match_model->get_table_name().'.status', '=', 'finished');
				})
				->where($cutoff_group_model->get_table_name().'.event_category_sport_category_id', '=', $data->event_category_sport_category_id)
				->orderBy('level', 'desc')
				->whereNull($cutoff_category_model->get_table_name().'.deleted_at')
				->whereNull($match_model->get_table_name().'.deleted_at')
				->first();
			if(!empty($last_cutoff_group)){
				// $last_match = MatchModel::where('cutoff_group_id', '=', $last_cutoff_group->id)->first();

				$default_score = 0;
				if(!empty($request->api_type)){
					if($request->api_type == "one_group" || $request->api_type == "last_group")
						$default_score = $data->cutoff_scoring_order == 'asc' ? 10000000000 : 0;
				}

				$temp = MatchEvent::select($match_event_model->get_table_name().'.registration_event_id')
					->selectRaw('MAX('.$match_event_model->get_table_name().'.id) as id')
					->join($match_model->get_table_name(), $match_event_model->get_table_name().'.match_id', '=', $match_model->get_table_name().'.id')
					->join($cutoff_group_model->get_table_name(), $match_model->get_table_name().'.cutoff_group_id', '=', $cutoff_group_model->get_table_name().'.id')
					->join($cutoff_category_model->get_table_name(), $cutoff_group_model->get_table_name().'.cutoff_category_id', '=', $cutoff_category_model->get_table_name().'.id');

				if(!empty($data->type))
					$temp = $temp->where($cutoff_group_model->get_table_name().'.type_id', '=', $data->type->id);

				$temp = $temp->where($cutoff_group_model->get_table_name().'.event_category_sport_category_id', '=', $data->event_category_sport_category->id)
					->whereNull($cutoff_category_model->get_table_name().'.deleted_at')
					->whereNull($match_model->get_table_name().'.deleted_at')
					->whereNull($cutoff_group_model->get_table_name().'.deleted_at')
					->groupBy($match_event_model->get_table_name().'.registration_event_id');

				if(!empty($request->sort_cutoff_category) && $data->event_category_sport_category->cutoff_match_type != 'multiple' && $data->event_category_sport_category->cutoff_separate_heat != 'yes')
					$temp = $temp->where($cutoff_category_model->get_table_name().'.id', '=', $request->sort_cutoff_category);
			}



			$temp1 = MatchEvent::select($match_event_model->get_table_name().'.*')
				->selectRaw($match_event_model->get_table_name().'.total_score '.($data->cutoff_scoring_order == 'asc' ? '/' : '*').' '.$cutoff_category_model->get_table_name().'.level as total_score_mod');

			if(!empty($temp))
				$temp1 = $temp1->joinSub($temp, 'temp', $match_event_model->get_table_name().'.id', '=', 'temp.id');

			$temp1 = $temp1->join($match_model->get_table_name(), $match_event_model->get_table_name().'.match_id', '=', $match_model->get_table_name().'.id')
				->join($cutoff_group_model->get_table_name(), $match_model->get_table_name().'.cutoff_group_id', '=', $cutoff_group_model->get_table_name().'.id')
				->join($cutoff_category_model->get_table_name(), $cutoff_group_model->get_table_name().'.cutoff_category_id', '=', $cutoff_category_model->get_table_name().'.id')
				->whereNull($cutoff_category_model->get_table_name().'.deleted_at')
				->whereNull($match_model->get_table_name().'.deleted_at')
				->whereNull($cutoff_group_model->get_table_name().'.deleted_at');


			$arr_member = CutoffGroupMember::select($cutoff_group_member_model->get_table_name().'.*',);

			if(!empty($temp1))
				$arr_member = $arr_member->selectRaw($match_event_model->get_table_name().'.total_score as total_score')
					->selectRaw($match_event_model->get_table_name().'.total_score_mod as total_score_mod')
					// ->selectRaw($match_event_model->get_table_name().'.rank as event_rank')
					->selectRaw('IF('.$match_event_model->get_table_name().'.rank IS NOT NULL, '.$match_event_model->get_table_name().'.rank, 1000) as event_rank')
					->leftJoinSub($temp1, $match_event_model->get_table_name(), $match_event_model->get_table_name().'.registration_event_id', '=', $cutoff_group_member_model->get_table_name().'.registration_event_id');

			$arr_member = $arr_member->where($cutoff_group_member_model->get_table_name().'.cutoff_group_id', '=', $data->id);

			if(!empty($request->api_type) && !empty($temp1)){
				if($request->api_type == "one_group" || $request->api_type == "last_group"){
					if($data->event_category_sport_category->cutoff_match_type == 'multiple' && $data->event_category_sport->cutoff_separate_heat == 'yes')
						$arr_member = $arr_member->orderBy('position', 'asc');
					else
						$arr_member = $arr_member->orderBy('event_rank', 'asc')->orderBy('position', 'asc');
				}
			}


			$arr_member = $arr_member->get();
			foreach($arr_member as $member){

				$this->cutoff_group_member($member, $request);
			}

			$arr_member = $cache_helper->get_key($arr_member, null, $request1, 'cutoff_group_member_'.$data->id, 3600, [ 'cutoff_group', 'member', $data->id, ]);
		}
		$data->member = $arr_member;
	}

	public function cutoff_group_member($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);



		$cutoff_group_model = new CutoffGroup();
		$cutoff_group_member_model = new CutoffGroupMember();

		$data->event_category_sport_category->event_category_sport->category_sport;
		$data->event_category_sport;
		$data->event;
		$data->cutoff_category;
		foreach($data->event_category_sport->coordinator as $coordinator){
			if(!empty($coordinator->coordinator))
				$coordinator->coordinator->type;
		}

		$cutoff_group = !empty($data) ? $data->cutoff_group : null;
		if(!empty($cutoff_group))
			$match = MatchModel::where('cutoff_group_id', '=', $cutoff_group->id)->first();
		$data->match = $match;

		if(!empty($match)){
			if(!empty($match->event_category_sport_venue))
				$match->event_category_sport_venue->venue;

			$certificate = Certificate::where('registration_event_id', '=', $data->registration_event->id)
				->first();
			$data->certificate = $certificate;

			$match_event = MatchEvent::where('match_id', '=', $match->id)
				->where('registration_event_id', '=', $data->registration_event->id)
				->first();
			$data->match_event = $match_event;

			$match_attendance = MatchAttendance::where('match_id', '=', $match->id)
				->where('registration_event_id', '=', $data->registration_event->id)
				->first();
			$data->match_attendance = $match_attendance;
		}


		$arr_cutoff_category = $cache_helper->get_key(null, null, $request1, 'cutoff_group_member_cutoff_category_'.$data->id, 3600, [ 'cutoff_group', 'member', 'cutoff_category', $data->id, ]);
		if(!isset($arr_cutoff_category)){
			if(isset($data->event_category_sport_category->cutoff_tournament_start_at) && $data->event_category_sport_category->cutoff_tournament_start_at != ""){
				// $cutoff_category_setting = $data->event_category_sport_category->cutoff_tournament_start_at;
				$cutoff_category_setting = CutoffCategory::find($data->event_category_sport_category->cutoff_tournament_start_at);
			}
			else{
				$setting = Setting::where('key', '=', 'cutoff_tournament_start_at')->first();
				$cutoff_category_setting = CutoffCategory::find($setting->value);
			}

			$arr_cutoff_category = CutoffCategory::where('level', $data->cutoff_group->event_category_sport_category->cutoff_match_type == 'multiple' ? '>=' : '=', $data->cutoff_group->cutoff_category->level);

			if($data->cutoff_group->event_category_sport_category->cutoff_match_type == 'multiple' && $data->cutoff_group->event_category_sport_category->cutoff_separate_heat == 'no')
				$arr_cutoff_category = $arr_cutoff_category->where('level', '<=', $cutoff_category_setting->level);

			$arr_cutoff_category = $arr_cutoff_category->get();

			if($data->cutoff_group->event_category_sport_category->cutoff_match_type == 'multiple' && $data->cutoff_group->event_category_sport_category->cutoff_separate_heat == 'yes')
				$arr_cutoff_category = [$arr_cutoff_category[0]];

			foreach($arr_cutoff_category as $key => $cutoff_category){
				$member = CutoffGroupMember::select($cutoff_group_member_model->get_table_name().'.*')
					->join($cutoff_group_model->get_table_name(), $cutoff_group_member_model->get_table_name().'.cutoff_group_id', '=', $cutoff_group_model->get_table_name().'.id')
					->where($cutoff_group_member_model->get_table_name().'.registration_event_id', '=', $data->registration_event->id)
					->where($cutoff_group_model->get_table_name().'.cutoff_category_id', '=', $cutoff_category->id)
					->whereNull($cutoff_group_model->get_table_name().'.deleted_at')
					->first();
				$arr_cutoff_category[$key]->is_qualified = !empty($member);

				$cutoff_group = !empty($member) ? $member->cutoff_group : null;
				if(!empty($cutoff_group))
					$match = MatchModel::where('cutoff_group_id', '=', $cutoff_group->id)
						->where('cutoff_category_id', '=', $cutoff_category->id)
						->first();
				$arr_cutoff_category[$key]->match = $match;

				if(!empty($match)){
					$match_event = MatchEvent::where('match_id', '=', $match->id)
						->where('registration_event_id', '=', $data->registration_event->id)
						->first();
					$arr_cutoff_category[$key]->match_event = $match_event;

					$match_attendance = MatchAttendance::where('match_id', '=', $match->id)
						->where('registration_event_id', '=', $data->registration_event->id)
						->first();
					$arr_cutoff_category[$key]->match_attendance = $match_attendance;
				}
			}

			$arr_cutoff_category = $cache_helper->get_key($arr_cutoff_category, null, $request1, 'cutoff_group_member_cutoff_category_'.$data->id, 3600, [ 'cutoff_group', 'member', 'cutoff_category', $data->id, ]);
		}
		$data->arr_cutoff_category = $arr_cutoff_category;

		if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type == $this->request_simple_str))
			$this->simple_registration_event($data->registration_event, $request);
		else
			$this->registration_event($data->registration_event, $request);
		$data->user = $data->registration_event->user;
		$data->registration_event->seed_time = !empty($data->seed_time) ? $data->seed_time : (count($data->registration_event->player) > 0 ? $data->registration_event->player[0]->seed_time : 0);


		$arr_match_desc = $cache_helper->get_key(null, null, $request1, 'cutoff_group_member_form_'.$data->id, 3600, [ 'cutoff_group', 'member', 'form', $data->id, ]);
		if(!isset($arr_match_desc)){
			$arr_match_desc = MatchModel::where(function($where) use($data) {
					$where = $where->orWhere('cutoff_group_member1_id', '=', $data->id)
						->orWhere('cutoff_group_member2_id', '=', $data->id);
				})
				->where('status', '=', 'finished')
				->orderBy('date', 'asc')
				->get();

			$arr_match_desc = $cache_helper->get_key($arr_match_desc, null, $request1, 'cutoff_group_member_form_'.$data->id, 3600, [ 'cutoff_group', 'member', 'form', $data->id, ]);
		}
		$data->arr_form = $arr_match_desc;

		if(!empty($request) && !empty($request->match_id)){
			$match_event = $cache_helper->get_key(null, null, $request1, 'cutoff_group_member_match_event_'.$request->match_id, 3600, [ 'cutoff_group', 'member', 'match_event', $request->match_id, ]);
			if(!isset($match_event)){
				$match_event = MatchEvent::where('match_id', '=', $request->match_id)
					->where('registration_event_id', '=', $data->registration_event->id)
					->first();

				$match_event = $cache_helper->get_key($match_event, null, $request1, 'cutoff_group_member_match_event_'.$request->match_id, 3600, [ 'cutoff_group', 'member', 'match_event', $request->match_id, ]);
			}
			$data->match_event = $match_event;
		}
	}

	public function group_member($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);



		if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type == $this->request_simple_str))
			$this->simple_registration_event($data->registration_event, $request);
		else
			$this->registration_event($data->registration_event, $request);
		$data->user = $data->registration_event->user;


		$arr_match_desc = $cache_helper->get_key(null, null, $request1, 'group_member_form_'.$data->id, 3600, [ 'group', 'member', 'form', $data->id, ]);
		if(!isset($arr_match_desc)){
			$arr_match_desc = MatchModel::where('group_id', '=', $data->group->id)
				->where(function($where) use($data) {
					$where = $where->orWhere('group_member1_id', '=', $data->id)
						->orWhere('group_member2_id', '=', $data->id);
				})
				->where('status', '=', 'finished')
				->orderBy('date', 'asc')
				->get();

			$arr_match_desc = $cache_helper->get_key($arr_match_desc, null, $request1, 'group_member_form_'.$data->id, 3600, [ 'group', 'member', 'form', $data->id, ]);
		}
		$data->arr_form = $arr_match_desc;




		$arr_opponent = $cache_helper->get_key(null, null, $request1, 'group_member_opponent_'.$data->id, 3600, [ 'group', 'member', 'opponent', $data->id, ]);
		if(!isset($arr_opponent)){
			$arr_match = MatchModel::where('group_id', '=', $data->group->id)->orderBy('date', 'asc')->get();
			$arr_opponent = [];
			foreach($arr_match as $match){
				if($match->member1->id == $data->id){
					$opponent_member = $match->member2;
					$opponent_member->match_date = $match->date;

					if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type == $this->request_simple_str))
						$this->simple_registration_event($opponent_member->registration_event, $request);
					else
						$this->registration_event($opponent_member->registration_event, $request);
					$opponent_member->user = $opponent_member->registration_event->user;
					array_push($arr_opponent, $opponent_member);
				}
				else if($match->member2->id == $data->id){
					$opponent_member = $match->member1;
					$opponent_member->match_date = $match->date;

					if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type == $this->request_simple_str))
						$this->simple_registration_event($opponent_member->registration_event, $request);
					else
						$this->registration_event($opponent_member->registration_event, $request);
					$opponent_member->user = $opponent_member->registration_event->user;
					array_push($arr_opponent, $opponent_member);
				}
			}

			$arr_opponent = $cache_helper->get_key($arr_opponent, null, $request1, 'group_member_opponent_'.$data->id, 3600, [ 'group', 'member', 'opponent', $data->id, ]);
		}
		$data->arr_opponent = $arr_opponent;
	}

	public function match($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);



		$total_question = 0;
		foreach($data->quiz as $quiz){
			$total_question += count($quiz->question);
			foreach($quiz->question as $question){
				$question->option;
			}
			$quiz_answer = QuizAnswer::where('match_id', '=', $data->id)->first();
			$quiz->allow_edit = empty($quiz_answer);
		}
		$data->quiz_status = count($data->quiz) > 0 && $total_question > 0 ? 'filled' : 'not_filled';
		$this->get_badge($data, 'quiz_status_format', __('general.'.$data->quiz_status), $data->quiz_status == 'filled' ? 'primary' : 'danger');
		$data->cutoff_category;


		$finished_match_event = MatchEvent::where('match_id', '=', $data->id)->where('type', '=', 'match_finished')->first();
		$data->is_finished = !empty($finished_match_event);
		$data->status_format = __('general.'.$data->status);
		$this->get_badge($data, 'status_format1', __('general.'.$data->status), $data->status == 'on_progress' ? 'warning' : 'primary');
		$data->date_format = !empty($data->date) ? $data->date->isoFormat($this->date_format) : 'No Date';

		$is_tournament_exist = false;
		if(!empty($data->cutoff_group) && $data->cutoff_group->event_category_sport->scoring_type->data == 'cutoff_tournament'){
			$tournament = Tournament::where('event_category_sport_category_id', $data->cutoff_group->event_category_sport_category_id)->first();
			$is_tournament_exist = !empty($tournament);
		}
		$data->is_tournament_exist = $is_tournament_exist;


		if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str)){
			if(!empty($data->group)){
				$data->group->event_category_sport_category->event_category_sport->category_sport;
				$data->group->event_category_sport_category->event_category_sport->scoring_type;
				$data->group->event_category_sport->scoring_type;
				$data->group->event_category_sport->category_sport;
				$data->group->venue;
				$data->group->type;
				foreach($data->group->event_category_sport->coordinator as $coordinator){
					if(!empty($coordinator->coordinator))
						$coordinator->coordinator->type;
				}
				$data->group->event;
				$data->event_category_sport = $data->group->event_category_sport;
				$this->group_member($data->member1, $request);
				$this->group_member($data->member2, $request);

				foreach($data->member1->registration_event->player as $player){
					$attendance = MatchAttendance::where('registration_event_player_id', $player->id)
						->where('match_id', $data->id)
						->first();
					$player->attendance = $attendance;
				}
				foreach($data->member2->registration_event->player as $player){
					$attendance = MatchAttendance::where('registration_event_player_id', $player->id)
						->where('match_id', $data->id)
						->first();
					$player->attendance = $attendance;
				}
			}
			else if(!empty($data->cutoff_group)){
				$data->cutoff_group->event_category_sport_category->event_category_sport->category_sport;
				$data->cutoff_group->event_category_sport_category->event_category_sport->scoring_type;
				$data->cutoff_group->event_category_sport->category_sport;
				$data->cutoff_group->event_category_sport->scoring_type;
				$data->cutoff_group->venue;
				$data->cutoff_group->type;
				$data->event_category_sport = $data->cutoff_group->event_category_sport;
				foreach($data->cutoff_group->event_category_sport->coordinator as $coordinator){
					if(!empty($coordinator->coordinator))
						$coordinator->coordinator->type;
				}
				$data->cutoff_group->event;
				$data->cutoff_group->cutoff_category;
				$arr_member = collect();
				foreach($data->cutoff_group->member as $member){
					if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type == $this->request_simple_str))
						$this->simple_registration_event($member->registration_event, $request);
					else
						$this->registration_event($member->registration_event, $request);

					$match_event = MatchEvent::where('match_id', '=', $data->id)->where('registration_event_id', '=', $member->registration_event->id)->first();
					$member->match_event = $match_event;
					$member->rank = !empty($match_event) ? $match_event->rank : 9999;
					$arr_member->push($member);

					foreach($member->registration_event->player as $player){
						$attendance = MatchAttendance::where('registration_event_player_id', $player->id)
							->where('match_id', $data->id)
							->first();
						$player->attendance = $attendance;
					}
				}
				// dd($arr_member->sortBy('rank')->values());
				$data->cutoff_group->arr_member = $arr_member->sortBy('rank')->values()->toArray();
			}
			else if(!empty($data->tournament)){
				$data->tournament->event_category_sport_category->event_category_sport->category_sport;
				$data->tournament->event_category_sport_category->event_category_sport->scoring_type;
				$data->tournament->event_category_sport->category_sport;
				$data->tournament->event_category_sport->scoring_type;
				$data->tournament->venue;
				$data->tournament->type;
				$data->event_category_sport = $data->tournament->event_category_sport;
				foreach($data->event_category_sport->coordinator as $coordinator){
					if(!empty($coordinator->coordinator))
						$coordinator->coordinator->type;
				}
				if(!empty($data->tournament->registration_event1)){
					// $data->tournament->registration_event1->user;
					// $data->tournament->registration_event1->player;
					$this->simple_registration_event($data->tournament->registration_event1, $request);
					// if(count($data->tournament->registration_event1->player) > 0)
					// 	$this->registration_event_player($data->tournament->registration_event1->player[0], $request);

					foreach($data->tournament->registration_event1->player as $player){
						$attendance = MatchAttendance::where('registration_event_player_id', $player->id)
							->where('match_id', $data->id)
							->first();
						$player->attendance = $attendance;
					}
				}
				if(!empty($data->tournament->registration_event2)){
					// $data->tournament->registration_event2->user;
					// $data->tournament->registration_event2->player;
					$this->simple_registration_event($data->tournament->registration_event2, $request);
					// if(count($data->tournament->registration_event2->player) > 0)
					// 	$this->registration_event_player($data->tournament->registration_event2->player[0], $request);

					foreach($data->tournament->registration_event2->player as $player){
						$attendance = MatchAttendance::where('registration_event_player_id', $player->id)
							->where('match_id', $data->id)
							->first();
						$player->attendance = $attendance;
					}
				}
				$data->tournament->level_format = __('general.level_'.$data->tournament->level);



			}
		}



		if(
			(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str)) &&
			$data->status == 'finished' && !empty($data->tournament)
		){
			if($data->tournament->registration_event1)
				$registration_event1 = $data->tournament->registration_event1;
			if($data->tournament->registration_event2)
				$registration_event2 = $data->tournament->registration_event2;

			if($data->tournament->registration_event1)
				$attendance_registration_event1 = MatchAttendance::where('registration_event_id', '=', $registration_event1->id)
					->where('match_id', '=', $data->id)
					->first();
			if($data->tournament->registration_event2)
				$attendance_registration_event2 = MatchAttendance::where('registration_event_id', '=', $registration_event2->id)
					->where('match_id', '=', $data->id)
					->first();

			// if($data->id == 'MATCH_20251018_000150')
			// 	dd((
			// 		!empty($attendance_registration_event1) && !empty($attendance_registration_event2) && (
			// 			($data->tournament->event_category_sport->cutoff_scoring_order == 'desc' && $data->group_member1_score > $data->group_member2_score) ||
			// 			($data->tournament->event_category_sport->cutoff_scoring_order == 'asc' && $data->group_member1_score < $data->group_member2_score)
			// 		)
			// 	) ||
			// 	(!empty($attendance_registration_event1) && empty($attendance_registration_event2)));


			if($data->tournament->registration_event2)
				$data->is_registration_event1_win =
				(
					!empty($attendance_registration_event1) && !empty($attendance_registration_event2) && (
						($data->tournament->event_category_sport->cutoff_scoring_order == 'desc' && $data->group_member1_score > $data->group_member2_score) ||
						($data->tournament->event_category_sport->cutoff_scoring_order == 'asc' && $data->group_member1_score < $data->group_member2_score)
					)
				) ||
				(!empty($attendance_registration_event1) && empty($attendance_registration_event2));
			else
				$data->is_registration_event1_win = !empty($data->tournament->registration_event1);

			if($data->tournament->registration_event1)
				$data->is_registration_event2_win =
					(
						!empty($attendance_registration_event1) && !empty($attendance_registration_event2) && (
							($data->tournament->event_category_sport->cutoff_scoring_order == 'desc' && $data->group_member1_score < $data->group_member2_score) ||
							($data->tournament->event_category_sport->cutoff_scoring_order == 'asc' && $data->group_member1_score > $data->group_member2_score)
						)
					) ||
					(empty($attendance_registration_event1) && !empty($attendance_registration_event2));
			else
				$data->is_registration_event2_win = !empty($data->tournament->registration_event2);
		}

		if(Auth::check() && Auth::user()->type->name == 'coordinator'){
			$allow_edit = false;
			$arr_coordinator = [];

			if(!empty($data->cutoff_group))
				$arr_coordinator = $data->cutoff_group->event_category_sport->coordinator;
			else if(!empty($data->tournament))
				$arr_coordinator = $data->tournament->event_category_sport->coordinator;
			else if(!empty($data->group))
				$arr_coordinator = $data->group->event_category_sport->coordinator;

			foreach($arr_coordinator as $coordinator){
				if(!empty($coordinator->coordinator) && $coordinator->coordinator->id == Auth::user()->id){
					$allow_edit = true;
					break;
				}
			}
			$data->allow_edit = $allow_edit;
		}

		$quiz_allow_edit = false;
		$quiz = count($data->quiz) > 0 ? $data->quiz[0] : null;
		if(Auth::check() && !empty($quiz)){
			$event_category_sport = !empty($data->cutoff_group) ? $data->cutoff_group->event_category_sport : $data->tournament->event_category_sport;
			foreach($event_category_sport->coordinator as $coordinator){
				if(Auth::user()->id == $coordinator->coordinator->id){
					$quiz_allow_edit = true;
					break;
				}
			}
			$type = !empty($data->cutoff_group) ? 'cutoff' : 'tournament_level_'.$data->tournament->level;
			$data->quiz_type = __('general.'.$type);
		}
		$data->quiz_allow_edit = $quiz_allow_edit;


		if(!empty($data->cutoff_group) && $data->cutoff_group->event_category_sport->scoring_type->data == 'manual'){
			$arr_match_event = $cache_helper->get_key(null, null, $request1, 'match_match_event_'.$data->id, 3600, [ 'match', 'match_event', $data->id, ]);
			if(!isset($arr_match_event)){
				$arr_match_event = MatchEvent::where('match_id', '=', $data->id)
					->where('type', '=', 'score')
					->orderBy('total_score', 'desc')
					->get();

				$arr_match_event = $cache_helper->get_key($arr_match_event, null, $request1, 'match_match_event_'.$data->id, 3600, [ 'match', 'match_event', $data->id, ]);
			}

			$data->arr_match_event = $arr_match_event;
		}


		if(empty($request->page) && (empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str))){







			if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str)){
				if(!empty($data->event_category_sport_venue))
					$data->event_category_sport_venue->venue;
				if(!empty($data->member1)){
					$this->registration_event($data->member1->registration_event, $request);
					$data->registration_event1_team_name = $data->member1->registration_event->team_name;
				}
				if(!empty($data->member2)){
					$this->registration_event($data->member2->registration_event, $request);
					$data->registration_event2_team_name = $data->member2->registration_event->team_name;
				}
					// if(!empty($data->tournament) && (empty($request) || (!empty($request) && $request->with_tournament)))
					// 	$this->tournament($data->tournament, $request);
				$data->coordinator;
				$data->cutoff_category;
				if(!empty($data->best_player))
					$this->registration_event_player($data->best_player, $request);
			}




			if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str)){
				if(!empty($data->tournament)){
					if($data->tournament->registration_event1){
						$registration_event1 = $data->tournament->registration_event1;
						if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str))
							$this->registration_event($data->tournament->registration_event1, $request);
					}
					if($data->tournament->registration_event2){
						$registration_event2 = $data->tournament->registration_event2;
						if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str))
							$this->registration_event($data->tournament->registration_event2, $request);
					}
				}
				else if(!empty($data->member1) && !empty($data->member2)){
					$registration_event1 = $data->member1->registration_event;
					$registration_event2 = $data->member2->registration_event;
				}
			}

			if(!empty($registration_event1))
				$attendance_registration_event1 = MatchAttendance::where('registration_event_id', '=', $registration_event1->id)
					->where('match_id', '=', $data->id)
					->first();
			if(!empty($registration_event2))
				$attendance_registration_event2 = MatchAttendance::where('registration_event_id', '=', $registration_event2->id)
					->where('match_id', '=', $data->id)
					->first();
			$data->is_registration_event1_attend = !empty($attendance_registration_event1);
			$data->is_registration_event2_attend = !empty($attendance_registration_event2);







		}
	}

	public function quiz($data, $request = null){
		foreach($data->question as $question)
			$question->option;
		$data->event_category_sport_category->event_category_sport->category_sport;
		$data->event_category_sport_category->event_category_sport->event;
		if(!empty($data->start_date)){
			$data->date_format = $data->start_date->isoFormat('DD/MM/YYYY');
			$data->time_format = $data->start_date->isoFormat('HH:mm');
			$data->start_date_format = $data->start_date->isoFormat('DD/MM/YYYY HH:mm');
		}
		if(!empty($data->end_date))
			$data->end_date_format = $data->end_date->isoFormat('DD/MM/YYYY HH:mm');
		$this->get_badge($data, 'status_format', __('general.'.$data->status), $data->status == 'on_progress' ? 'warning' : 'primary');
		$data->status_type = $data->type;
		$data->type = __('general.'.$data->type);

		if(!empty($request)){
			if(!empty($request->registration_event_id)){
				$event = MatchEvent::where('match_id', '=', $data->match->id)
					->where('registration_event_id', '=', $request->registration_event_id)
					->first();
				$data->score = !empty($event) ? $event->total_score : 'Quiz not taken';
				$data->rank = !empty($event) ? $event->rank : 'Quiz not taken';

				$order = Order::where('registration_event_id', '=', $request->registration_event_id)->orderBy('status_level', 'desc')->first();
				$registration_event = RegistrationEvent::find($request->registration_event_id);
				$this->quiz_status($order, $registration_event, $data->match);
				$data->status_match_format = $registration_event->status_match_format;
				$data->status_match = $registration_event->status_match;
			}
		}
	}

	public function match_event($data, $request = null){
		$data->type_format = __('general.'.$data->type);
		$this->match($data->match, $request);
		if(!empty($data->registration_event_player))
			$this->registration_event_player($data->registration_event_player, $request);
		if(!empty($data->group_member))
			$data->group_member->group;
		if(!empty($data->registration_event)){
			$this->registration_event($data->registration_event, $request);
			$data->registration_event_team_name = $data->registration_event->team_name;
		}
	}

	public function match_attendance($data, $request = null){
		$data->date_format = $data->created_at->isoFormat($this->date_format);
		$this->match($data->match, $request);
		if(!empty($data->registration_event_player))
			$this->registration_event_player($data->registration_event_player, $request);
		if(!empty($data->registration_event))
			$this->registration_event($data->registration_event, $request);
	}

	public function simple_rel($data, $request = null){
		$data->created_at_format = $data->created_at->isoFormat($this->date_format);
	}

	public function venue($data, $request = null){

	}

	public function order($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);

		// if(empty($data->registration_event))
		// 	dd($data);

		if(!empty($data->registration_event)){
			$registration = $cache_helper->get_key(null, null, $request1, 'registration_'.$data->id, 3600, [ 'registration', $data->registration_event->id, ]);
			if(!isset($registration)){
				$this->registration_event($data->registration_event, $request);

				$registration = $cache_helper->get_key($data->registration_event, null, $request1, 'registration_'.$data->id, 3600, [ 'registration', $data->registration_event->id, ]);
			}
			$data->registration_event = $registration;
		}



		$data->user;
		$data->detail;
		$data->payment_method;

		$data->id_format = $this->wrap_text($data->id, 'truncate_start');
		$data->user_email_format = $this->wrap_text($data->user->email);

		$data->event;
		if(!empty($data->event_category_sport_category))
			$data->event_category_sport_category->event_category_sport->category_sport;
		$data->total_price_format = "Rp. ".number_format($data->total_price, 0, ',', '.');
		$data->created_at_format = $data->created_at->isoFormat($this->date_format);
		$data->status_format1 = __('general.'.$data->status);
		$this->get_badge($data, 'status_format', __('general.'.$data->status), $data->status == 'success' ? 'primary' : ($data->status == 'wait_payment' ? 'warning text-white' : 'danger'), [], 'status');
		$this->get_badge($data, 'registration_type_format', __('general.'.$data->registration_type_format), $data->registration_type_format == 'live' ? 'primary' : 'danger', [], 'registration_type');
		$data->status_not_cancel = $data->status != 'canceled' && $data->status != 'success';
		$data->status_wait_payment = $data->status == 'wait_payment';
		$data->expired_at_format = !empty($data->payment_expired_at) ? $data->payment_expired_at->isoFormat($this->date_format) : '-';
		$data->paid_at_format = !empty($data->paid_at) ? $data->paid_at->isoFormat($this->date_format) : 'Not Paid';
	}

	public function calculate_total_registration($data, $type = null, $request = null){
		$cache_helper = new CacheHelper();

		$user_model = new User();
		$type_model = new Type();
		$registration_event_model = new RegistrationEvent();
		$event_category_sport_category_model = new EventCategorySportCategory();
		$event_category_sport_model = new EventCategorySport();
		$order_model = new Order();
		$order_detail_model = new OrderDetail();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);

		$total_registration_all = $cache_helper->get_key(null, 'calculate_total_registration_'.$type.'_all', $request1, 'calculate_total_registration_'.$type.'_all_'.$data->id, 3600, ['calculate_total_registration', 'category_sport', $data->id, 'all']);
		$total_registration_not_canceled = $cache_helper->get_key(null, 'calculate_total_registration_'.$type.'_not_canceled', $request1, 'calculate_total_registration_'.$type.'_not_canceled_'.$data->id, 3600, ['calculate_total_registration', 'category_sport', $data->id, 'not_canceled']);
		$total_registration_canceled = $cache_helper->get_key(null, 'calculate_total_registration_'.$type.'_canceled', $request1, 'calculate_total_registration_'.$type.'_canceled_'.$data->id, 3600, ['calculate_total_registration', 'category_sport', $data->id, 'canceled']);
		$total_registration_success = $cache_helper->get_key(null, 'calculate_total_registration_'.$type.'_success', $request1, 'calculate_total_registration_'.$type.'_success_'.$data->id, 3600, ['calculate_total_registration', 'category_sport', $data->id, 'success']);
		$total_registration_wait_payment = $cache_helper->get_key(null, 'calculate_total_registration_'.$type.'_wait_payment', $request1, 'calculate_total_registration_'.$type.'_wait_payment_'.$data->id, 3600, ['calculate_total_registration', 'category_sport', $data->id, 'wait_payment']);
		if(!isset($total_registration_all)){
			$total_registration = RegistrationEvent::select($registration_event_model->get_table_name().'.*')
				->join($user_model->get_table_name(), $registration_event_model->get_table_name().'.user_id', '=', $user_model->get_table_name().'.id')
				->join($type_model->get_table_name(), $user_model->get_table_name().'.type_id', '=', $type_model->get_table_name().'.id')
				->join($event_category_sport_category_model->get_table_name(), $registration_event_model->get_table_name().'.event_category_sport_category_id', '=', $event_category_sport_category_model->get_table_name().'.id')
				->join($event_category_sport_model->get_table_name(), $event_category_sport_category_model->get_table_name().'.event_category_sport_id', '=', $event_category_sport_model->get_table_name().'.id')
				->whereNull($user_model->get_table_name().'.deleted_at')
				->whereNull($type_model->get_table_name().'.deleted_at')
				->whereNull($event_category_sport_category_model->get_table_name().'.deleted_at')
				->whereNull($event_category_sport_model->get_table_name().'.deleted_at')
				->where($registration_event_model->get_table_name().'.type', '=', 'live');

			if($data instanceof EventCategorySportCategory)
				$total_registration = $total_registration->where($event_category_sport_category_model->get_table_name().'.id', '=', $data->id);
			else if($data instanceof EventCategorySport)
				$total_registration = $total_registration->where($event_category_sport_model->get_table_name().'.id', '=', $data->id);

			if(!empty($type))
				$total_registration = $total_registration->where($type_model->get_table_name().'.name', 'like', $type);

			if(!empty($request) && !empty($request->venue_id))
				$total_registration = $total_registration->where($registration_event_model->get_table_name().'.venue_id', '=', $request->venue_id);


			$total_registration_all = clone $total_registration;
			$total_registration_not_canceled = clone $total_registration;
			$total_registration_canceled = clone $total_registration;
			$total_registration_success = clone $total_registration;
			$total_registration_wait_payment = clone $total_registration;

			$total_registration_all = $total_registration_all->get()->count();
			$total_registration_not_canceled = $total_registration_not_canceled->where($registration_event_model->get_table_name().'.status', '!=', 'canceled')->get()->count();
			$total_registration_canceled = $total_registration_canceled->where($registration_event_model->get_table_name().'.status', '=', 'canceled')->get()->count();
			$total_registration_success = $total_registration_success->where($registration_event_model->get_table_name().'.status', '=', 'success')->get()->count();
			// dd($total_registration_wait_payment->where($registration_event_model->get_table_name().'.status', '=', 'wait_payment')->get());
			$total_registration_wait_payment = $total_registration_wait_payment->where($registration_event_model->get_table_name().'.status', '=', 'wait_payment')->get()->count();



			$total_registration_all = $cache_helper->get_key($total_registration_all, 'calculate_total_registration_'.$type.'_all', $request1, 'calculate_total_registration_'.$type.'_all_'.$data->id, 3600, ['calculate_total_registration', 'category_sport', $data->id, 'all']);
			$total_registration_not_canceled = $cache_helper->get_key($total_registration_not_canceled, 'calculate_total_registration_'.$type.'_not_canceled', $request1, 'calculate_total_registration_'.$type.'_not_canceled_'.$data->id, 3600, ['calculate_total_registration', 'category_sport', $data->id, 'not_canceled']);
			$total_registration_canceled = $cache_helper->get_key($total_registration_canceled, 'calculate_total_registration_'.$type.'_canceled', $request1, 'calculate_total_registration_'.$type.'_canceled_'.$data->id, 3600, ['calculate_total_registration', 'category_sport', $data->id, 'canceled']);
			$total_registration_success = $cache_helper->get_key($total_registration_success, 'calculate_total_registration_'.$type.'_success', $request1, 'calculate_total_registration_'.$type.'_success_'.$data->id, 3600, ['calculate_total_registration', 'category_sport', $data->id, 'success']);
			$total_registration_wait_payment = $cache_helper->get_key($total_registration_wait_payment, 'calculate_total_registration_'.$type.'_wait_payment', $request1, 'calculate_total_registration_'.$type.'_wait_payment_'.$data->id, 3600, ['calculate_total_registration', 'category_sport', $data->id, 'wait_payment']);
		}

		$data->{'total' . (!empty($type) ? '_'.$type : '') . '_registration'} = $total_registration_all;
		$data->{'total' . (!empty($type) ? '_'.$type : '') . '_registration_not_canceled'} = $total_registration_not_canceled;
		$data->{'total' . (!empty($type) ? '_'.$type : '') . '_registration_canceled'} = $total_registration_canceled;
		$data->{'total' . (!empty($type) ? '_'.$type : '') . '_registration_success'} = $total_registration_success;
		$data->{'total' . (!empty($type) ? '_'.$type : '') . '_registration_wait_payment'} = $total_registration_wait_payment;
	}

	public function event_category_sport_category($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);


		$user_model = new User();
		$type_model = new Type();
		$registration_event_model = new RegistrationEvent();
		$event_category_sport_category_model = new EventCategorySportCategory();
		$event_category_sport_model = new EventCategorySport();
		$order_model = new Order();
		$order_detail_model = new OrderDetail();


		$data->event_category_sport->category_sport;
		$data->event_category_sport->event;
		$data->event_category_sport->scoring_type;
		$data->number_mod = !empty($data->number_mod) ? $data->number_mod : '-';
		$data->register = [];
		if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != 'award')){
			$this->calculate_total_registration($data, 'school', $request);
			$this->calculate_total_registration($data, 'club', $request);
			$this->calculate_total_registration($data, 'personal', $request);
			$this->calculate_total_registration($data, null, $request);

		}
		// $temp1 = Order::select($order_model->get_table_name().'.*')
		//   ->join($order_detail_model->get_table_name(), $order_detail_model->get_table_name().'.order_id', '=', $order_model->get_table_name().'.id')
		//   ->where($order_detail_model->get_table_name().'.detail', 'like', 'team payment')
		//   ->where($order_model->get_table_name().'.status', '!=', 'canceled');
		$data->is_match_generated = count($data->group) > 0 || count($data->cutoff_group) > 0 || count($data->tournament) > 0;



		if(!empty($request->rel_type) && $request->rel_type == 'award'){
			$arr_registration_award = $cache_helper->get_key(null, null, $request1, 'event_category_sport_category_award_'.$data->id, 3600, [ 'event_category_sport_category', 'award', $data->id, ]);
			if(!isset($arr_registration_award)){
				$arr_venue = EventCategorySportVenue::where('event_category_sport_id', $data->event_category_sport->id)->get();

				$arr_registration_award = [];
				foreach($arr_venue as $venue){

					$arr_registration = RegistrationEvent::where('event_category_sport_category_id', $data->id)
						->where('venue_id', $venue->venue->id)
						->where('status', 'success')
						->where('type', 'live')
						->get();
					$arr_registration1 = [];


					$event_category_sport_category_controller = new EventCategorySportCategoryController();
					$req = new Request();
					$req->merge([
						'event_category_sport_id' => $data->event_category_sport->id,
						'id' => $data->id,
						'return_type' => 'array',
						'rel_type' => 'simple',
					]);
					$arr = $event_category_sport_category_controller->index_cutoff_member($req);

					foreach($arr_registration as $registration){
						$rank_override = null;
						if($data->event_category_sport->cutoff_seed_time == 'yes'){
							foreach($arr[0]['arr'] as $key => $cutoff_group_member){
								if($cutoff_group_member->registration_event->id == $registration->id){
									$rank_override = $key + 1;
									break;
								}
							}
						}

						$this->get_award($registration, null, ["get_rank" => false, "rank_override" => $rank_override, ]);

						if($registration->award != __('general.participant')){
							$registration->player;
							array_push($arr_registration1, $registration);
						}
					}
					// dd($arr_registration1);

					// dd(collect($arr_registration1)->sortBy('rank')->values());
					$arr_registration = collect($arr_registration1)->sortBy('rank')->values();
					array_push($arr_registration_award, [
						"venue" => $venue,
						"arr_registration" => $arr_registration,
					]);
				}

				$arr_registration_award = $cache_helper->get_key($arr_registration_award, null, $request1, 'event_category_sport_category_award_'.$data->id, 3600, [ 'event_category_sport_category', 'award', $data->id, ]);
			}
			$data->arr_registration_award = $arr_registration_award;
		}
		else if(!empty($request->rel_type) && $request->rel_type == 'without_match'){
			unset($data->group);
			unset($data->cutoff_group);
			unset($data->tournament);
			// unset($data->event_category_sport);
		}
	}

	public function event_category_sport($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);


		$user_model = new User();
		$type_model = new Type();
		$registration_event_model = new RegistrationEvent();
		$event_category_sport_category_model = new EventCategorySportCategory();
		$event_category_sport_model = new EventCategorySport();
		$order_model = new Order();
		$order_detail_model = new OrderDetail();


		$data->category_sport;
		$data->scoring_type;
		$data->id_format = $this->wrap_text($data->id, 'truncate_start');

		if(!empty($data->certificate_file_name)){
			$data->certificate_image_format = '<img src="'.(!empty($data->certificate_file_name) ? url('/media/event/category-sport/certificate?file_name='.$data->certificate_file_name) : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
			$data->url_certificate_image = !empty($data->certificate_file_name) ? url('/media/event/category-sport/certificate?file_name='.$data->certificate_file_name) : url('/image/no_image_available.jpeg');
		}

		if(!empty($data->name_tag_file_name)){
			$data->name_tag_image_format = '<img src="'.(!empty($data->name_tag_file_name) ? url('/media/event/category-sport/name-tag?file_name='.$data->name_tag_file_name) : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
			$data->url_name_tag_image = !empty($data->name_tag_file_name) ? url('/media/event/category-sport/name-tag?file_name='.$data->name_tag_file_name) : url('/image/no_image_available.jpeg');
		}

		if(
			empty($request) || (
				!empty($request) && (
					empty($request->rel_type2) || (!empty($request->rel_type2) && $request->rel_type2 != 'simple2')
				)
			)
		){
			$total_max_registration = 0;
			if(!empty($request) && $request->rel_type != $this->request_simple_str){
				$total_registered = 0;
				$total_registered_not_canceled = 0;
				$total_registered_success = 0;
				$total_registered_canceled = 0;
				$total_registered_wait_payment = 0;

				$is_match_generated = false;

				foreach($data->category as $category){
					$category1 = $cache_helper->get_key(null, null, $request1, 'event_category_sport_category_'.$category->id, 3600, [ 'event_category_sport', 'category', $category->id, ]);
					if(!isset($category1)){
						$this->event_category_sport_category($category, $request);

						$category1 = $cache_helper->get_key($category, null, $request1, 'event_category_sport_category_'.$category->id, 3600, [ 'event_category_sport', 'category', $category->id, ]);
					}
					$category = $category1;

					if(!empty($request->rel_type) && $request->rel_type != 'award'){
						$total_registered += $category->total_school_registration + $category->total_club_registration + $category->total_personal_registration;
						$total_registered_not_canceled += $category->total_school_registration_not_canceled + $category->total_club_registration_not_canceled + $category->total_personal_registration_not_canceled;
						$total_registered_canceled += $category->total_school_registration_canceled + $category->total_club_registration_canceled + $category->total_personal_registration_canceled;
						$total_registered_success += $category->total_school_registration_success + $category->total_club_registration_success + $category->total_personal_registration_success;
						$total_registered_wait_payment += $category->total_school_registration_wait_payment + $category->total_club_registration_wait_payment + $category->total_personal_registration_wait_payment;
						$total_max_registration += $category->max_total_team_per_club + $category->max_total_team_per_school;
					}

					if($category->is_match_generated)
						$is_match_generated = true;
				}

				$data->total_registration = $total_registered;
				$data->total_registration_not_canceled = $total_registered_not_canceled;
				$data->total_registration_canceled = $total_registered_canceled;
				$data->total_registration_success = $total_registered_success;
				$data->total_registration_wait_payment = $total_registered_wait_payment;
			}
			else{
				$this->calculate_total_registration($data, null, $request);

				foreach($data->category as $category)
					$total_max_registration += $category->max_total_team_per_club + $category->max_total_team_per_school;
			}
			// dd($total_registered_wait_payment);
			// dd($data);
			$data->total_registered = $data->total_registration;
			$data->total_registered_not_canceled = $data->total_registration_not_canceled;
			$data->total_registered_canceled = $data->total_registration_canceled;
			$data->total_registered_success = $data->total_registration_success;
			$data->total_registered_wait_payment = $data->total_registration_wait_payment;
			$data->total_max_registration = $total_max_registration;
			$data->is_match_generated = count($data->group) > 0 || count($data->cutoff_group) > 0 || count($data->tournament) > 0;
			$data->match_generated_format = $data->is_match_generated ? '<span class="badge badge-primary">Generated</span>' : '<span class="badge badge-danger">Not Generated</span>';
			$data->match_completed_format = $data->is_match_completed == 1 ? '<span class="badge badge-primary">Completed</span>' : '<span class="badge badge-danger">Not Completed</span>';
			$data->status_commit_format = $data->is_certificate_open == 1 ? '<span class="badge badge-primary">Committed</span>' : '<span class="badge badge-danger">Not Committed</span>';
			$data->status_match_not_completed = $data->is_match_completed == 0;
			$data->status_commit = $data->is_certificate_open == 1 && $data->is_match_generated;
			$data->status_not_commit = $data->is_certificate_open == 0 && $data->is_match_generated;
			$data->status_recommit = $data->is_certificate_open == 0 && !empty($data->last_certificate_open_at) && $data->is_match_generated;
			$data->last_certificate_open_format = !empty($data->last_certificate_open_at) ? $data->last_certificate_open_at->isoFormat($this->date_format) : '';
		}

		if(!empty($request->rel_type) && $request->rel_type == 'without_match'){
			unset($data->group);
			unset($data->cutoff_group);
			unset($data->tournament);
		}
		if(!empty($request->rel_type2) && $request->rel_type2 == 'simple2'){
			unset($data->venue);
			unset($data->category);
		}


		$data->coordinator;
		$data->is_show = $data->is_show == 1;
		$data->is_not_show = $data->is_show == 0;
		$data->is_show_format = $data->is_show ? '<span class="badge badge-primary">Show</span>' : '<span class="badge badge-danger">Hidden</span>';
		$data->event;
		$data->scoring_type;
		$data->created_at_format = $data->created_at->isoFormat($this->date_format);
		if(!empty($request) && $request->rel_type != $this->request_simple_str && $request->rel_type != 'award'){
			$arr_venue = $cache_helper->get_key(null, null, $request1, 'event_category_sport_venue_'.$data->id, 3600, [ 'event_category_sport', 'venue', $data->id, ]);
			if(!isset($arr_venue)){
				$arr_venue = $data->venue;
				foreach($arr_venue as $venue){
					$arr_registration = RegistrationEvent::select($registration_event_model->get_table_name().'.*')
						->join($event_category_sport_category_model->get_table_name(), $registration_event_model->get_table_name().'.event_category_sport_category_id', '=', $event_category_sport_category_model->get_table_name().'.id')
						->join($event_category_sport_model->get_table_name(), $event_category_sport_category_model->get_table_name().'.event_category_sport_id', '=', $event_category_sport_model->get_table_name().'.id')
						->whereNull($event_category_sport_category_model->get_table_name().'.deleted_at')
						->whereNull($event_category_sport_model->get_table_name().'.deleted_at')
						->where($registration_event_model->get_table_name().'.venue_id', '=', $venue->venue->id)
						->where($event_category_sport_model->get_table_name().'.id', '=', $data->id)
						->where($registration_event_model->get_table_name().'.status', '=', 'success')
						->where($registration_event_model->get_table_name().'.type', '=', 'live')
						->get();

					if($request->rel_type != 'without_match'){
						$venue->venue->arr_registration = $arr_registration;
					}
					else{
						$venue->venue;
						$venue->total_registration = count($arr_registration);
					}
				}

				$arr_venue = $cache_helper->get_key($arr_venue, null, $request1, 'event_category_sport_venue_'.$data->id, 3600, [ 'event_category_sport', 'venue', $data->id, ]);
			}
			$data->venue = $arr_venue;

			if(!empty($data->best_player))
				$this->registration_event($data->best_player, $request);
			if(!empty($data->top_scorer))
				$this->registration_event($data->top_scorer, $request);
		}
	}

	public function registration_event_player($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);


		$match_model = new MatchModel();
		$cutoff_category_model = new CutoffCategory();
		$cutoff_group_model = new CutoffGroup();
		$cutoff_group_member_model = new CutoffGroupMember();
		$group_member_model = new GroupMember();
		$tournament_model = new Tournament();
		$registration_event_model = new RegistrationEvent();
		$registration_event_player_model = new RegistrationEventPlayer();

		$data->id_format = $this->wrap_text($data->id);
		$data->created_at_format = $data->created_at->isoFormat($this->date_format);
		// $this->get_image($data, '/property');
		$data->image_format = '<img src="'.(!empty($data->file_name) ? url('/media/registration/player?file_name='.$data->file_name) : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
		$data->url_image = !empty($data->file_name) ? url('/media/registration/player?file_name='.$data->file_name) : url('/image/no_image_available.jpeg');
		$data->gender_format = $data->gender == 1 ? 'Male' : 'Female';
		$data->name = !empty($data->name) ? $data->name : '-';
		$data->birth_date_format = !empty($data->birth_date) && $data->birth_date != "" ? $data->birth_date->isoFormat($this->date_only_format) : '-';
		$data->player_position;

		if(!empty($data->registration_event->event_category_sport_category) && !empty($data->registration_event->event_category_sport_category->event_category_sport)){
			$data->registration_event->event_category_sport_category->event_category_sport->event;
			$data->registration_event->event_category_sport_category->event_category_sport->category_sport;
		}
		$data->registration_event->user->type;
		$data->registration_event->player_position;
		$data->registration_event->status_format = __('general.'.$data->registration_event->status);
		$data->registration_event->original_team_name = !empty($data->registration_event->team_name) ? $data->registration_event->team_name : $data->registration_event->user->name;

		$data->original_team_name = $data->registration_event->original_team_name;
		if(!empty($data->registration_event->event_category_sport_category) && $data->registration_event->event_category_sport_category->display_team == 'player'){
			$data->registration_event->team_name = $data->name;
			$data->team_name = $data->name;
		}

		if(!empty($data->registration_event->venue) && !empty($data->registration_event->event_category_sport_category))
			$event_category_sport_venue = EventCategorySportVenue::where('event_category_sport_id', $data->registration_event->event_category_sport_category->event_category_sport_id)
				->where('venue_id', $data->registration_event->venue->id)
				->first();
		if(!empty($event_category_sport_venue) && !empty($data->registration_event->event_category_sport_category)){
			if(!empty($event_category_sport_venue->start_date))
				$data->registration_event->event_category_sport_category->event_category_sport->start_date = $event_category_sport_venue->start_date;
			if(!empty($event_category_sport_venue->end_date))
				$data->registration_event->event_category_sport_category->event_category_sport->end_date = $event_category_sport_venue->end_date;
		}

		$certificate = Certificate::where('registration_event_player_id', $data->id)->first();
		if(!empty($certificate)){
			$data->award = $certificate->award;
			$data->rank = $certificate->rank;
		}


		// dd((empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str)) && !isset($data->award));
		if((empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str)) && !isset($data->award)){
			$this->get_award($data->registration_event, $data);


		}


		CarbonInterval::setCascadeFactors([
			'second' => [1000, 'milliseconds'],
			'minute' => [60, 'seconds'],
			'hour' => [60, 'minutes'],
			'day' => [24, 'hours'],
			'week' => [7, 'days'],
			// in this example the cascade won't go farther than week unit
		]);

		$seed_time_minute = CarbonInterval::milliseconds($data->seed_time * 1000)->cascade()->minutez;
		$seed_time_second = CarbonInterval::milliseconds($data->seed_time * 1000)->cascade()->secondz;
		$seed_time_milisecond = substr(CarbonInterval::milliseconds($data->seed_time * 1000)->cascade()->format('%F'), 0, 3);


		$data->seed_time_format = $data->seed_time > 0 ? (
				($seed_time_minute < 10 ? '0'.$seed_time_minute : $seed_time_minute).':'.
				($seed_time_second < 10 ? '0'.$seed_time_second : $seed_time_second).
				($seed_time_milisecond > 0 ? '.'.$seed_time_milisecond : '')
			) : 'NT';
		$data->allow_dispensation = true;
		$data->allow_delete = !empty($data->registration_event->event_category_sport_category) && !empty($data->registration_event->event_category_sport_category->event_category_sport) && $data->registration_event->event_category_sport_category->event_category_sport->is_certificate_open == 0;


		$total_score = $cache_helper->get_key(null, null, $request1, 'player_total_score_'.$data->id, 3600, [ 'player', 'total_score', $data->id, ]);
		$total_yellow_card = $cache_helper->get_key(null, null, $request1, 'player_total_yellow_card_'.$data->id, 3600, [ 'player', 'total_yellow_card', $data->id, ]);
		$total_red_card = $cache_helper->get_key(null, null, $request1, 'player_total_red_card_'.$data->id, 3600, [ 'player', 'total_red_card', $data->id, ]);
		$total_best_player = $cache_helper->get_key(null, null, $request1, 'player_total_best_player_'.$data->id, 3600, [ 'player', 'total_best_player', $data->id, ]);
		if(!isset($total_score)){
			$total_score = 0;
			$total_yellow_card = 0;
			$total_red_card = 0;
			$total_best_player = 0;

			$arr_match_event = MatchEvent::where('registration_event_player_id', '=', $data->id)
				->orderBy('match_id', 'asc')
				->orderBy('minute_time', 'asc');
			if(!empty($request->match_id))
				$arr_match_event = $arr_match_event->where('match_id', '=', $request->match_id);
			$arr_match_event = $arr_match_event->get();


			foreach($arr_match_event as $match_event){
				if($match_event->type == "score")
					$total_score += $match_event->total_score;
				else if($match_event->type == "yellow_card")
					$total_yellow_card++;
				else if($match_event->type == "red_card")
					$total_red_card++;
			}
			$total_best_player = MatchModel::where('best_player_id', '=', $data->id)->get()->count();


			$total_score = $cache_helper->get_key($total_score, null, $request1, 'player_total_score_'.$data->id, 3600, [ 'player', 'total_score', $data->id, ]);
			$total_yellow_card = $cache_helper->get_key($total_yellow_card, null, $request1, 'player_total_yellow_card_'.$data->id, 3600, [ 'player', 'total_yellow_card', $data->id, ]);
			$total_red_card = $cache_helper->get_key($total_red_card, null, $request1, 'player_total_red_card_'.$data->id, 3600, [ 'player', 'total_red_card', $data->id, ]);
			$total_best_player = $cache_helper->get_key($total_best_player, null, $request1, 'player_total_best_player_'.$data->id, 3600, [ 'player', 'total_best_player', $data->id, ]);
		}
		$data->total_best_player = $total_best_player;
		$data->total_score = $total_score;
		$data->total_yellow_card = $total_yellow_card;
		$data->total_red_card = $total_red_card;




		// $scoring_type_category_sport = ScoringTypeCategorySport::where('category_sport_id', '=', $data->registration_event->event_category_sport_category->event_category_sport->category_sport->id)
		// 	->where('event_id', '=', $data->registration_event->event->id)
		// 	->first();

		if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str)){
			$last_match = $cache_helper->get_key(null, null, $request1, 'player_last_match_'.$data->id, 3600, [ 'player', 'last_match', $data->id, ]);
			if(!isset($last_match)){
				$cutoff_member_temp = CutoffGroupMember::select('cutoff_group_id')
					->selectRaw('MAX(id) as id')
					->where('registration_event_id', '=', $data->registration_event->id)
					->groupBy('cutoff_group_id');

				$tournament_temp = Tournament::select('id', 'deleted_at')
					->where(function($where) use($tournament_model, $data) {
						$where = $where->orWhere($tournament_model->get_table_name().'.registration_event1_id', '=', $data->registration_event->id)
							->orWhere($tournament_model->get_table_name().'.registration_event2_id', '=', $data->registration_event->id);
					});

				$group_member_temp = GroupMember::select('id', 'deleted_at')
					->where(function($where) use($group_member_model, $data) {
						$where = $where->orWhere($group_member_model->get_table_name().'.registration_event_id', '=', $data->registration_event->id);
					});

				$last_match = MatchModel::select($match_model->get_table_name().'.*')
					->leftJoin($cutoff_group_model->get_table_name(), $match_model->get_table_name().'.cutoff_group_id', '=', $cutoff_group_model->get_table_name().'.id')
					->leftJoinSub($cutoff_member_temp, $cutoff_group_member_model->get_table_name(), $cutoff_group_member_model->get_table_name().'.cutoff_group_id', '=', $cutoff_group_model->get_table_name().'.id')
					->leftJoinSub($tournament_temp, $tournament_model->get_table_name(), function($join) use($tournament_model, $match_model, $data) {
						$join = $join->on($match_model->get_table_name().'.tournament_id', '=', $tournament_model->get_table_name().'.id');
					})
					->leftJoinSub($group_member_temp, 'group_member1', function($join) use($match_model, $data) {
						$join = $join->on($match_model->get_table_name().'.group_member1_id', '=', 'group_member1.id');
					})
					->leftJoinSub($group_member_temp, 'group_member2', function($join) use($match_model, $data) {
						$join = $join->on($match_model->get_table_name().'.group_member2_id', '=', 'group_member2.id');
					})

					->whereNull($cutoff_group_model->get_table_name().'.deleted_at')
					// ->whereNull($tournament_model->get_table_name().'.deleted_at')
					// ->whereNull('group_member1.deleted_at')
					// ->whereNull('group_member2.deleted_at')

					->orderBy('date', 'desc')
					->first();


				if(!empty($last_match)){
					$achievement = '';
					if(!empty($last_match->cutoff_group))
						$achievement = 'Round '.$last_match->cutoff_group->cutoff_category->name;
					else if(!empty($last_match->tournament))
						$achievement = __('general.level_'.$last_match->tournament->level);
					else if(!empty($last_match->group))
						$achievement = 'Group';
					$last_match->achievement = $achievement;
				}

				$last_match = $cache_helper->get_key($last_match, null, $request1, 'player_last_match_'.$data->id, 3600, [ 'player', 'last_match', $data->id, ]);
			}
			$data->last_match = $last_match;

			if(!empty($data->registration_event->award))
				$data->award = $data->registration_event->award;
			$data->group_member = $data->registration_event->group_member;

			if(!empty($request->match_id)){
				$match_attendance = $cache_helper->get_key(null, null, $request1, 'player_match_attendance_'.$request->match_id, 3600, [ 'player', 'match_attendance', $request->match_id, ]);
				if(!isset($match_attendance)){
					$match_attendance = MatchAttendance::where('match_id', '=', $request->match_id)
						->where('registration_event_player_id', '=', $data->id)
						->first();

					$match_attendance = $cache_helper->get_key($match_attendance, null, $request1, 'player_match_attendance_'.$request->match_id, 3600, [ 'player', 'match_attendance', $request->match_id, ]);
				}
				$data->already_attendance = !empty($match_attendance);
			}
		}
	}

	public function get_award($data, $registration_event_player = null, $arr_additional = []){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);


		$cutoff_category_model = new CutoffCategory();
		$cutoff_group_model = new CutoffGroup();
		$cutoff_group_member_model = new CutoffGroupMember();
		$group_member_model = new GroupMember();
		$match_event_model = new MatchEvent();
		$match_model = new MatchModel();
		$tournament_model = new Tournament();


		$event_category_sport = $data->event_category_sport_category->event_category_sport;
		// dd($data->event_category_sport_category->event_category_sport->cutoff_seed_time == 'yes');
		if($event_category_sport->scoring_type->data == 'league'){
			$group_member = GroupMember::where('registration_event_id', '=', $data->id)->first();
			if(!empty($group_member)){
				$arr_group_member = $cache_helper->get_key(null, null, $request1, 'award_group_member_'.$group_member->group->id, 3600, [ 'award', 'group_member', $group_member->group->id, ]);
				if(!isset($arr_group_member)){
					$arr_group_member = GroupMember::where('group_id', '=', $group_member->group->id)
						->orderBy('point', 'desc')
						->orderBy('goal_difference', 'desc')
						->get();

					foreach($arr_group_member as $key => $member){
						if($group_member->id == $member->id){
							$arr_match_event_id = $cache_helper->get_key(null, null, $request1, 'award_match_event_id_'.$group_member->id, 3600, [ 'award', 'match_event_id', $group_member->id, ]);
							$arr_total_score = $cache_helper->get_key(null, null, $request1, 'award_total_score_'.$group_member->id, 3600, [ 'award', 'total_score', $group_member->id, ]);
							if(!isset($arr_match_event_id)){
								$arr_match = MatchModel::where(function($where) use($group_member) {
									$where = $where->orWhere('group_member1_id', $group_member->id)
										->orWhere('group_member2_id', $group_member->id);
								})->get();
								$arr_match_event_id = [];
								$arr_total_score = [];
								foreach($arr_match as $match){
									$match_event = MatchEvent::where('registration_event_id', $group_member->registration_event_id)->first();
									if(!empty($match_event)){
										array_push($arr_match_event_id, $match_event->id);
										array_push($arr_total_score, $match_event->total_score);
									}
								}

								$arr_match_event_id = $cache_helper->get_key($arr_match_event_id, null, $request1, 'award_match_event_id_'.$group_member->id, 3600, [ 'award', 'match_event_id', $group_member->id, ]);
								$arr_total_score = $cache_helper->get_key($arr_total_score, null, $request1, 'award_total_score_'.$group_member->id, 3600, [ 'award', 'total_score', $group_member->id, ]);
							}

							$data->award = (__('general.numbering_'.($key + 1))).' Place'.($key + 1 <= 3 ? ' / '.__('general.medal_'.($key + 1)) : '');
							$data->rank = $key;
							$data->match_name = "Group";
							$data->match_event_score = $arr_total_score;
							$data->match_event_id = $arr_match_event_id;
							break;
						}
					}

					$arr_group_member = $cache_helper->get_key($arr_group_member, null, $request1, 'award_group_member_'.$group_member->group->id, 3600, [ 'award', 'group_member', $group_member->group->id, ]);
				}

				$group_member->group;
				if(!empty($registration_event_player))
					$registration_event_player->group_member = $group_member;
			}

		}
		else if($event_category_sport->scoring_type->data == 'cutoff'){
			$rank_override = !empty($arr_additional['rank_override']) ? $arr_additional['rank_override'] : null;
			if(
				$event_category_sport->cutoff_seed_time == 'yes' && (
					empty($rank_override)
				)
			){
				$arr = $cache_helper->get_key(null, null, $request1, 'award_cutoff_group_rank_'.$data->id, 3600, [ 'award', 'cutoff_group', 'rank', $data->id, ]);
				if(!isset($arr)){
					$event_category_sport_category_controller = new EventCategorySportCategoryController();
					$req = new Request();
					$req->merge([
						'event_category_sport_id' => $event_category_sport->id,
						'id' => $data->event_category_sport_category->id,
						'registration_event_id' => $data->id,
						'return_type' => 'array',
						'rel_type' => 'simple',
					]);
					$arr = $event_category_sport_category_controller->index_cutoff_member($req);
					// dd($arr);

					// dd($arr);
					if(count($arr) > 0){
						foreach($arr[0]['arr'] as $key => $cutoff_group_member){
							if($cutoff_group_member->registration_event->id == $data->id){
								$rank_override = $key + 1;
								break;
							}
						}
					}

					$arr = $cache_helper->get_key($arr, null, $request1, 'award_cutoff_group_rank_'.$data->id, 3600, [ 'award', 'cutoff_group', 'rank', $data->id, ]);
				}
			}
			// dd('test');



			$group_member = $cache_helper->get_key(null, null, $request1, 'award_cutoff_group_match_event_'.$data->id, 3600, [ 'award', 'cutoff_group', 'match_event', $data->id, ]);
			if(!isset($group_member)){
				$group_member = MatchEvent::select($match_event_model->get_table_name().'.id', $match_event_model->get_table_name().'.rank', $match_event_model->get_table_name().'.match_id', $match_event_model->get_table_name().'.total_score', $cutoff_category_model->get_table_name().'.level as level')
					->join($match_model->get_table_name(), $match_event_model->get_table_name().'.match_id', '=', $match_model->get_table_name().'.id')
					->join($cutoff_group_model->get_table_name(), $match_model->get_table_name().'.cutoff_group_id', '=', $cutoff_group_model->get_table_name().'.id')
					->join($cutoff_category_model->get_table_name(), $cutoff_group_model->get_table_name().'.cutoff_category_id', '=', $cutoff_category_model->get_table_name().'.id')
					->where($match_event_model->get_table_name().'.registration_event_id', '=', $data->id)
					->where($cutoff_group_model->get_table_name().'.event_category_sport_category_id', '=', $data->event_category_sport_category->id)
					->whereNull($match_model->get_table_name().'.deleted_at')
					->whereNull($cutoff_group_model->get_table_name().'.deleted_at')
					->whereNull($cutoff_category_model->get_table_name().'.deleted_at')
					->orderBy('rank', 'asc')
					->orderBy('level', 'desc')
					->first();

				$group_member = $cache_helper->get_key($group_member, null, $request1, 'award_cutoff_group_match_event_'.$data->id, 3600, [ 'award', 'cutoff_group', 'match_event', $data->id, ]);
			}


			if(!empty($group_member)){
				if(!empty($rank_override))
					$group_member->rank = $rank_override;

				// dd($group_member);
				if($group_member->rank <= 3 && $group_member->rank >= 1)
					$data->award = __('general.numbering_'.$group_member->rank).' Place'.(
						$event_category_sport->category_sport->type == 'fun_sport' ? '' : ' / '.__('general.medal_'.($group_member->rank))
					);
				else if($group_member->rank > 3 && $group_member->rank <= 8)
					$data->award = __('general.numbering_'.$group_member->rank).' Place';
				else if($group_member->rank > 8)
					$data->award = __('general.participant');
				else
					$data->award = __('general.participant');
				$data->rank = $group_member->rank;
				$data->match_name = $group_member->match->cutoff_group->name;
				$data->match_event_score = $group_member->total_score;
				$data->match_event_id = $group_member->id;

				if(!empty($registration_event_player))
					$registration_event_player->group_member = $group_member;
			}
			else{
				$data->award = __('general.participant');
				$data->rank = 1000;
				$data->match_name = 'No Cutoff';
				$data->match_event_score = 0;
				$data->match_event_id = null;
			}
		}
		else if($event_category_sport->scoring_type->data == 'manual'){
			$group_member = $cache_helper->get_key(null, null, $request1, 'award_manual_match_event_'.$data->id, 3600, [ 'award', 'manual', 'match_event', $data->id, ]);
			if(!isset($group_member)){
				$group_member = MatchEvent::select($match_event_model->get_table_name().'.*', $cutoff_category_model->get_table_name().'.level')
					->join($match_model->get_table_name(), $match_event_model->get_table_name().'.match_id', '=', $match_model->get_table_name().'.id')
					->join($cutoff_group_model->get_table_name(), $match_model->get_table_name().'.cutoff_group_id', '=', $cutoff_group_model->get_table_name().'.id')
					->join($cutoff_category_model->get_table_name(), $cutoff_group_model->get_table_name().'.cutoff_category_id', '=', $cutoff_category_model->get_table_name().'.id')
					->where($match_event_model->get_table_name().'.registration_event_id', '=', $data->id)
					->where($cutoff_group_model->get_table_name().'.event_category_sport_category_id', '=', $data->event_category_sport_category->id)
					->whereNull($match_model->get_table_name().'.deleted_at')
					->whereNull($cutoff_group_model->get_table_name().'.deleted_at')
					->whereNull($cutoff_category_model->get_table_name().'.deleted_at')
					->orderBy('rank', 'asc')
					->orderBy('level', 'desc')
					->first();

				$group_member = $cache_helper->get_key($group_member, null, $request1, 'award_manual_match_event_'.$data->id, 3600, [ 'award', 'manual', 'match_event', $data->id, ]);
			}

			if(!empty($group_member)){
				if($group_member->rank <= 3 && $group_member->rank >= 1)
					$data->award = __('general.numbering_'.$group_member->rank).' Place'.($group_member->rank <= 3 ? ' / '.__('general.medal_'.($group_member->rank)) : '');
				else if($group_member->rank <= 6 && $group_member->rank > 3)
					$data->award = __('general.runner_up'.($group_member->rank - 3));
				else if($group_member->rank > 6)
					$data->award = __('general.participant');
				else
					$data->award = __('general.participant');
				$data->rank = $group_member->rank;
				$data->match_name = $group_member->match->cutoff_group->name;
				$data->match_event_score = $group_member->total_score;
				$data->match_event_id = $group_member->id;

				if(!empty($registration_event_player))
					$registration_event_player->group_member = $group_member;
			}
			else{
				$data->award = __('general.participant');
				$data->rank = 1000;
				$data->match_name = 'No Cutoff';
				$data->match_event_score = 0;
				$data->match_event_id = null;
			}
		}
		else if(
			$event_category_sport->scoring_type->data == 'tournament' ||
			$event_category_sport->scoring_type->data == 'tournament_no_group' ||
			$event_category_sport->scoring_type->data == 'cutoff_tournament'
		){
			$tournament = $cache_helper->get_key(null, null, $request1, 'award_tournament_'.$data->id, 3600, [ 'award', 'tournament', $data->id, ]);
			if(!isset($tournament)){
				$tournament = Tournament::where(function($where) use($data) {
					$where = $where->orWhere('registration_event1_id', '=', $data->id)
						->orWhere('registration_event2_id', '=', $data->id);
				})
					->orderBy('level', 'asc')
					->first();

				$tournament = $cache_helper->get_key($tournament, null, $request1, 'award_tournament_'.$data->id, 3600, [ 'award', 'tournament', $data->id, ]);
			}

			if(!empty($tournament)){
				$match = MatchModel::where('tournament_id', '=', $tournament->id)->first();
				$match_event = MatchEvent::where('match_id', '=', $match->id)->where('registration_event_id', $data->id)->first();

				if($tournament->level == 1){
					if(!empty($tournament->registration_event1)){
						$team_score = $tournament->registration_event1->id == $data->id ? $match->group_member1_score : $match->group_member2_score;
						$opponent_score = $tournament->registration_event1->id == $data->id ? $match->group_member2_score : $match->group_member1_score;
					}
					else{
						$team_score = 0;
						$opponent_score = 0;
					}

					if($tournament->is_third_place == 1){
						$data->award = ($tournament->event_category_sport->cutoff_scoring_order == 'desc' && $team_score > $opponent_score) || ($tournament->event_category_sport->cutoff_scoring_order == 'asc' && $team_score < $opponent_score) ?
							__('general.numbering_3').' Place'.(
								$event_category_sport->category_sport->type == 'fun_sport' ? '' : ' / '.__('general.medal_3')
							) : (
								$tournament->event_category_sport->scoring_type->data == 'tournament' ? __('general.runner_up') : __('general.participant')
							);
						$data->rank = ($tournament->event_category_sport->cutoff_scoring_order == 'desc' && $team_score > $opponent_score) ||
							($tournament->event_category_sport->cutoff_scoring_order == 'asc' && $team_score < $opponent_score) ? 3 : (
								$tournament->event_category_sport->scoring_type->data == 'tournament' ? 4 : 5
							);
					}
					else{
						$data->award = ($tournament->event_category_sport->cutoff_scoring_order == 'desc' && $team_score > $opponent_score) ||
							($tournament->event_category_sport->cutoff_scoring_order == 'asc' && $team_score < $opponent_score) ?
							__('general.numbering_1').' Place'.(
								$event_category_sport->category_sport->type == 'fun_sport' ? '' : ' / '.__('general.medal_1')
							) :
							__('general.numbering_2').' Place'.(
								$event_category_sport->category_sport->type == 'fun_sport' ? '' : ' / '.__('general.medal_2')
							);
						$data->rank = ($tournament->event_category_sport->cutoff_scoring_order == 'desc' && $team_score > $opponent_score) ||
							($tournament->event_category_sport->cutoff_scoring_order == 'asc' && $team_score < $opponent_score) ? 1 : 2;
					}

				}
				else{
					// if($tournament->counter <= 3)
					// 	$data->award = __('general.numbering_3').' Place / '.__('general.medal_3');
					// else if($tournament->counter > 3){
						if($event_category_sport->scoring_type->data == 'tournament')
							$data->award = __('general.runner_up');
						else if($event_category_sport->scoring_type->data == 'tournament_no_group' && $tournament->level == 2)
							$data->award = __('general.numbering_3').' Place'.(
								$event_category_sport->category_sport->type == 'fun_sport' ? '' : ' / '.__('general.medal_3')
							);
						else
							$data->award = $event_category_sport->category_sport->type == 'fun_sport' ? __('general.numbering_3').' Place' : __('general.participant');
						$data->rank = $event_category_sport->scoring_type->data == 'tournament' || $event_category_sport->scoring_type->data == 'tournament_no_group' ? 3 : 4;
					// }
					// $data->award = __('general.level_'.$tournament->level).'ist';
				}

				if(!empty($match_event)){
					$data->match_name = $match_event->match->name;
					$data->match_event_score = $match_event->total_score;
					$data->match_event_id = $match_event->id;
				}
			}
			else{
				$group_member = null;
				if($event_category_sport->scoring_type->data == 'tournament'){
					$group_member = GroupMember::where('registration_event_id', '=', $data->id)->first();
					if(!empty($group_member)){
						$arr_group_member = $cache_helper->get_key(null, null, $request1, 'award_group_member_'.$group_member->group->id, 3600, [ 'award', 'group_member', $group_member->group->id, ]);
						if(!isset($arr_group_member)){
							$arr_group_member = GroupMember::where('group_id', '=', $group_member->group->id)
								->orderBy('point', 'desc')
								->orderBy('goal_difference', 'desc')
								->get();

							$arr_group_member = $cache_helper->get_key($arr_group_member, null, $request1, 'award_group_member_'.$group_member->group->id, 3600, [ 'award', 'group_member', $group_member->group->id, ]);
						}

						foreach($arr_group_member as $key => $member){
							if($group_member->id == $member->id){
								$arr_match_event_id = $cache_helper->get_key(null, null, $request1, 'award_match_event_id_'.$group_member->id, 3600, [ 'award', 'match_event_id', $group_member->id, ]);
								$arr_total_score = $cache_helper->get_key(null, null, $request1, 'award_total_score_'.$group_member->id, 3600, [ 'award', 'total_score', $group_member->id, ]);
								if(!isset($arr_match_event_id)){
									$arr_match = MatchModel::where(function($where) use($group_member) {
										$where = $where->orWhere('group_member1_id', $group_member->id)
											->orWhere('group_member2_id', $group_member->id);
									})->get();


									$arr_match_event_id = [];
									$arr_total_score = [];
									foreach($arr_match as $match){
										$match_event = MatchEvent::where('registration_event_id', $group_member->registration_event_id)->first();
										if(!empty($match_event)){
											array_push($arr_match_event_id, $match_event->id);
											array_push($arr_total_score, $match_event->total_score);
										}
									}

									$arr_match_event_id = $cache_helper->get_key($arr_match_event_id, null, $request1, 'award_match_event_id_'.$group_member->id, 3600, [ 'award', 'match_event_id', $group_member->id, ]);
									$arr_total_score = $cache_helper->get_key($arr_total_score, null, $request1, 'award_total_score_'.$group_member->id, 3600, [ 'award', 'total_score', $group_member->id, ]);
								}

								$data->award = __('general.participant');
								$data->rank = $key + 1;
								$data->match_name = "Group";
								$data->match_event_score = $arr_total_score;
								$data->match_event_id = $arr_match_event_id;
								// $data->award = ($key + 1).' Place';
								break;
							}
						}
						$group_member->group;
					}
					else{
						$data->award = 'Not Started';
						$data->rank = 1000;
						$data->match_name = "No Group";
						$data->match_event_score = 0;
						$data->match_event_id = null;
					}
				}
				else if($event_category_sport->scoring_type->data == 'cutoff_tournament'){
					$group_member = CutoffGroupMember::where('registration_event_id', '=', $data->id)
						->orderBy('created_at', 'desc')
						->first();

					if(!empty($group_member)){
						$match_event = $cache_helper->get_key(null, null, $request1, 'award_match_event_'.$data->id, 3600, [ 'award', 'match_event', $data->id, ]);
						if(!isset($match_event)){
							$match_event = MatchEvent::select($match_event_model->get_table_name().'.*', $cutoff_category_model->get_table_name().'.level as level')
								->join($match_model->get_table_name(), $match_event_model->get_table_name().'.match_id', '=', $match_model->get_table_name().'.id')
								->join($cutoff_group_model->get_table_name(), $match_model->get_table_name().'.cutoff_group_id', '=', $cutoff_group_model->get_table_name().'.id')
								->join($cutoff_category_model->get_table_name(), $cutoff_group_model->get_table_name().'.cutoff_category_id', '=', $cutoff_category_model->get_table_name().'.id')
								->where($match_event_model->get_table_name().'.registration_event_id', '=', $data->id)
								->where($match_model->get_table_name().'.cutoff_group_id', '=', $group_member->cutoff_group->id)
								->whereNull($match_model->get_table_name().'.deleted_at')
								->whereNull($cutoff_group_model->get_table_name().'.deleted_at')
								->whereNull($cutoff_category_model->get_table_name().'.deleted_at')
								->orderBy('rank', 'asc')
								->orderBy('level', 'desc')
								->first();

							$match_event = $cache_helper->get_key($match_event, null, $request1, 'award_match_event_'.$data->id, 3600, [ 'award', 'match_event', $data->id, ]);
						}

						$data->award = __('general.participant');
						$data->rank = 1000;
						// $data->award = 'Round '.$group_member->cutoff_group->cutoff_category->name;
						$data->match_name = $match_event->match->cutoff_group->name;
						$data->match_event_score = $match_event->total_score;
						$data->match_event_id = $match_event->id;

						$data->group_member = $group_member;
					}
					else{
						$data->award = 'Not Started';
						$data->rank = 1000;
						$data->match_name = 'No Cutoff';
						$data->match_event_score = 0;
						$data->match_event_id = null;
					}
				}

				if(!empty($registration_event_player))
					$registration_event_player->group_member = $group_member;
			}
		}

		if(!empty($registration_event_player))
			$registration_event_player->rank = $data->rank;
	}

	public function registration_event_coach($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);


		$data->image_format = '<img src="'.(!empty($data->file_name) ? url('/media/registration/coach?file_name='.$data->file_name) : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
		$data->url_image = !empty($data->file_name) ? url('/media/registration/coach?file_name='.$data->file_name) : url('/image/no_image_available.jpeg');
		$data->id_format = $this->wrap_text($data->id);
		$data->gender_format = $data->gender == 1 ? 'Male' : 'Female';
		$data->name = !empty($data->name) ? $data->name : '-';
		$data->birth_date_format = !empty($data->birth_date) && $data->birth_date != "" ? $data->birth_date->isoFormat($this->date_only_format) : '-';
		$data->coach_position;
		$data->registration_event->event_category_sport_category->event_category_sport->event;
		$data->registration_event->event_category_sport_category->event_category_sport->category_sport;
		$data->registration_event->status_format = __('general.'.$data->registration_event->status);
		$data->allow_delete = $data->registration_event->event_category_sport_category->event_category_sport->is_certificate_open == 0;

		$event_category_sport_venue = $cache_helper->get_key(null, null, $request1, 'coach_venue_'.$data->id, 3600, [ 'coach', 'venue', $data->id, ]);
		if(!isset($event_category_sport_venue)){
			$event_category_sport_venue = EventCategorySportVenue::where('event_category_sport_id', $data->registration_event->event_category_sport_category->event_category_sport->id)
				->where('venue_id', $data->registration_event->venue->id)
				->first();

			$event_category_sport_venue = $cache_helper->get_key($event_category_sport_venue, null, $request1, 'coach_venue_'.$data->id, 3600, [ 'coach', 'venue', $data->id, ]);
		}
		if(!empty($event_category_sport_venue)){
			if(!empty($event_category_sport_venue->start_date))
				$data->registration_event->event_category_sport_category->event_category_sport->start_date = $event_category_sport_venue->start_date;
			if(!empty($event_category_sport_venue->end_date))
				$data->registration_event->event_category_sport_category->event_category_sport->end_date = $event_category_sport_venue->end_date;
		}

		if($data->registration_event->event_category_sport_category->display_team == 'player')
			$data->registration_event->team_name = count($data->registration_event->player) > 0 ? $data->registration_event->player[0]->name : 'No Player Inputted';
	}

	public function registration_event_match($data, $request = null){
		$data->name = !empty($data->name) ? $data->name : '-';
		$data->status_format = __('general.'.$data->status_format);
		if(!empty($data->order_detail))
			$data->order_detail->order->status_format = __('general.'.$data->order_detail->order->status);
		$data->registration_event->event_category_sport_category->event_category_sport->event;
		$data->registration_event->event_category_sport_category->event_category_sport->category_sport;
	}

	public function simple_registration_event($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);


		$data->created_at_format = $data->created_at->isoFormat($this->date_format);
		$data->image_format = '<img src="'.(!empty($data->file_name) ? url('/media/registration/team?file_name='.$data->file_name) : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
		$data->url_image = !empty($data->file_name) ? url('/media/registration/team?file_name='.$data->file_name) : url('/image/no_image_available.jpeg');
		$data->user->type;

		if(empty($data->original_team_name))
			$data->original_team_name = !empty($data->team_name) ? $data->team_name : $data->user->name;
		if($data->event_category_sport_category->display_team == 'player'){
			$data->team_name = count($data->player) > 0 ? $data->player[0]->name : $data->user->name;
			if(count($data->player) > 0){
				$data->url_image = !empty($data->player[0]->file_name) ? url('/media/registration/player?file_name='.$data->player[0]->file_name) : url('/image/no_image_available.jpeg');
				$data->image_format = '<img src="'.$data->url_image.'" style="width: 10rem;"/>';
			}
		}


		if(empty($request->page)){
			$str_player_name = '';
			$url_image_player = '';
			if($data->event_category_sport_category->display_team == 'player'){
				foreach($data->player as $key => $player){

					$player->player_position;
					$player->image_format = '<img src="'.(!empty($player->file_name) ? url('/media/registration/player?file_name='.$player->file_name) : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
					$player->url_image = !empty($player->file_name) ? url('/media/registration/player?file_name='.$player->file_name) : url('/image/no_image_available.jpeg');
					$str_player_name .= ($key == 0 ? '' : ', ').$player->name;
					if($key == 0)
						$url_image_player = $player->url_image;
				}

				if(count($data->player) > 0 && $data->event_category_sport_category->cutoff_seed_time == 'yes'){
					$data->seed_time = $data->player[0]->seed_time;

					CarbonInterval::setCascadeFactors([
						'second' => [1000, 'milliseconds'],
						'minute' => [60, 'seconds'],
						'hour' => [60, 'minutes'],
						'day' => [24, 'hours'],
						'week' => [7, 'days'],
						// in this example the cascade won't go farther than week unit
					]);

					$seed_time_minute = CarbonInterval::milliseconds($data->seed_time * 1000)->cascade()->minutez;
					$seed_time_second = CarbonInterval::milliseconds($data->seed_time * 1000)->cascade()->secondz;
					$seed_time_milisecond = substr(CarbonInterval::milliseconds($data->seed_time * 1000)->cascade()->format('%F'), 0, 3);


					$data->seed_time_format = $data->seed_time > 0 ? (
							($seed_time_minute < 10 ? '0'.$seed_time_minute : $seed_time_minute).':'.
							($seed_time_second < 10 ? '0'.$seed_time_second : $seed_time_second).
							($seed_time_milisecond > 0 ? '.'.$seed_time_milisecond : '')
						) : 'NT';
				}

				$data->match_event = MatchEvent::where('registration_event_id', '=', $data->id)->orderBy('created_at', 'desc')->first();
				// if($data->event_category_sport_category->max_player < $this->max_player_team_name){
					if(empty($data->original_team_name))
						$data->original_team_name = !empty($data->team_name) ? $data->team_name : $data->user->name;
					if($data->event_category_sport_category->display_team == 'player')
						$data->team_name = count($data->player) > 0 ? $data->player[0]->name : $data->user->name;

					$data->team_name = ucfirst($data->team_name);
					$data->url_image = $url_image_player;
					$data->image_format = '<img src="'.(!empty($url_image_player) ? $url_image_player : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
				// }

			}
		}
	}

	public function quiz_status($order, $data, $match){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);



		$order_detail_model = new OrderDetail();
		$quiz_answer_model = new QuizAnswer();
		$quiz_question_model = new QuizQuestion();
		$quiz_model = new Quiz();
		$match_model = new MatchModel();


		$quiz_answer = $cache_helper->get_key(null, null, $request1, 'quiz_status_answer_'.$match->id, 3600, [ 'quiz_status', 'answer', $match->id, ]);
		$quiz_question = $cache_helper->get_key(null, null, $request1, 'quiz_status_question_'.$match->id, 3600, [ 'quiz_status', 'question', $match->id, ]);
		$quiz = $cache_helper->get_key(null, null, $request1, 'quiz_status_quiz_'.$match->id, 3600, [ 'quiz_status', 'quiz', $match->id, ]);
		if(!isset($quiz_answer)){
			$quiz_answer = QuizAnswer::select($quiz_answer_model->get_table_name().'.*')
				->join($quiz_question_model->get_table_name(), $quiz_answer_model->get_table_name().'.quiz_question_id', '=', $quiz_question_model->get_table_name().'.id')
				->join($quiz_model->get_table_name(), $quiz_question_model->get_table_name().'.quiz_id', '=', $quiz_model->get_table_name().'.id')
				->where($quiz_answer_model->get_table_name().'.registration_event_id', '=', $data->id)
				->where($quiz_model->get_table_name().'.match_id', '=', $match->id)
				->whereNull($quiz_question_model->get_table_name().'.deleted_at')
				->whereNull($quiz_model->get_table_name().'.deleted_at')
				->first();
			$quiz_question = QuizQuestion::select($quiz_question_model->get_table_name().'.*')
				->join($quiz_model->get_table_name(), $quiz_question_model->get_table_name().'.quiz_id', '=', $quiz_model->get_table_name().'.id')
				->where($quiz_model->get_table_name().'.match_id', '=', $match->id)
				->whereNull($quiz_model->get_table_name().'.deleted_at')
				->first();
			$quiz = Quiz::where('match_id', '=', $match->id)->first();

			$quiz_answer = $cache_helper->get_key($quiz_answer, null, $request1, 'quiz_status_answer_'.$match->id, 3600, [ 'quiz_status', 'answer', $match->id, ]);
			$quiz_question = $cache_helper->get_key($quiz_question, null, $request1, 'quiz_status_question_'.$match->id, 3600, [ 'quiz_status', 'question', $match->id, ]);
			$quiz = $cache_helper->get_key($quiz, null, $request1, 'quiz_status_quiz_'.$match->id, 3600, [ 'quiz_status', 'quiz', $match->id, ]);
		}

		$event_category_sport = !empty($match->cutoff_group) ? $match->cutoff_group->event_category_sport : $match->tournament->event_category_sport;

		if(!empty($order) && $order->status == 'success'){
			if(!empty($quiz_question) && count($data->player) > 0 && !empty($event_category_sport) && !empty($quiz) && $event_category_sport->start_date < $quiz->start_date && $event_category_sport->end_date > $quiz->end_date){
				if(empty($quiz_answer))
					$data->status_match = 'start' . (!empty($match) && !empty($match->tournament) ? '_tournament_level_'.$match->tournament->level : '');
				else
					$data->status_match = 'finished';
			}
			else if(count($data->player) == 0)
				$data->status_match = 'no_player_added';
			else
				$data->status_match = 'not_available';
		}
		else if(!empty($order) && $order->status == 'wait_payment')
			$data->status_match = 'wait';
		else
			$data->status_match = 'canceled';

		if(str_contains($data->status_match, 'start'))
			$class_badge = 'primary';
		else if($data->status_match == 'finished')
			$class_badge = 'disabled';
		else if($data->status_match == 'wait')
			$class_badge = 'warning text-white';
		else
			$class_badge = 'danger';

		$this->get_badge($data, 'status_match_format',
			__('general.'.$data->status_match),
			$class_badge,
			!empty($match) ? [
				[
					"name" => 'Start',
					"link" => '/quiz?match_id='.$match->id.'&registration_event_id='.$data->id,
				],
			] : [],
			'status_match',
		);
	}

	public function registration_event($data, $request = null){
		$cache_helper = new CacheHelper();

		$request1 = new Request();
		$request1->merge([ "rel_type" => "simple" , ]);



		$order_model = new Order();
		$order_detail_model = new OrderDetail();
		$quiz_answer_model = new QuizAnswer();
		$quiz_question_model = new QuizQuestion();
		$quiz_model = new Quiz();
		$match_model = new MatchModel();
		$cutoff_group_model = new CutoffGroup();
		$cutoff_group_member_model = new CutoffGroupMember();
		$tournament_model = new Tournament();

		$data->created_at_format = $data->created_at->isoFormat($this->date_format);
		$data->image_format = '<img src="'.(!empty($data->file_name) ? url('/media/registration/team?file_name='.$data->file_name) : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
		$data->url_image = !empty($data->file_name) ? url('/media/registration/team?file_name='.$data->file_name) : url('/image/no_image_available.jpeg');
		$data->user->type;
		$data->id_format = $this->wrap_text($data->id);
		$data->id_format2 = $this->wrap_text($data->id, 'truncate_start');
		$data->user_email_format = $this->wrap_text($data->user->email);

		$event_category_sport_venue = $cache_helper->get_key(null, null, $request1, 'registration_venue_'.$data->id, 3600, [ 'registration', 'venue', $data->id, ]);
		if(!isset($event_category_sport_venue) && !empty($data->event_category_sport_category)){
			$event_category_sport_venue = EventCategorySportVenue::where('event_category_sport_id', $data->event_category_sport_category->event_category_sport_id);
			if(!empty($data->venue))
				$event_category_sport_venue = $event_category_sport_venue->where('venue_id', $data->venue->id);
			$event_category_sport_venue = $event_category_sport_venue->first();

			if(!empty($event_category_sport_venue)){
				if(!empty($event_category_sport_venue->start_date))
					$data->event_category_sport_category->event_category_sport->start_date = $event_category_sport_venue->start_date;
				if(!empty($event_category_sport_venue->end_date))
					$data->event_category_sport_category->event_category_sport->end_date = $event_category_sport_venue->end_date;
			}

			$event_category_sport_venue = $cache_helper->get_key($event_category_sport_venue, null, $request1, 'registration_venue_'.$data->id, 3600, [ 'registration', 'venue', $data->id, ]);
		}



		// dd(empty($data->original_team_name));
		if(empty($data->original_team_name))
			$data->original_team_name = !empty($data->team_name) ? $data->team_name : $data->user->name;
		if(!empty($data->event_category_sport_category) && $data->event_category_sport_category->display_team == 'player'){
			// if(empty($data->team_name))
				$data->team_name = count($data->player) > 0 ? $data->player[0]->name : $data->user->name;

			// $str_player_name = '';
			// $url_image_player = '';
			// foreach($data->player as $key => $player){
			// 	$player->player_position;
			// 	$player->image_format = '<img src="'.(!empty($player->file_name) ? url('/media/registration/player?file_name='.$player->file_name) : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
			// 	$player->url_image = !empty($player->file_name) ? url('/media/registration/player?file_name='.$player->file_name) : url('/image/no_image_available.jpeg');
			// 	$str_player_name .= ($key == 0 ? '' : ', ').$player->name;
			// 	if($key == 0)
			// 		$url_image_player = $player->url_image;
			// }

			// $data->team_name = $str_player_name != '' ? $str_player_name : 'No Player Inputted';
			if(count($data->player) > 0){
				$data->url_image = !empty($data->player[0]->file_name) ? url('/media/registration/player?file_name='.$data->player[0]->file_name) : url('/image/no_image_available.jpeg');
				$data->image_format = '<img src="'.$data->url_image.'" style="width: 10rem;"/>';
			}
		}
		$data->team_name = ucfirst($data->team_name);
		if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str && $request->rel_type != 'simple2')){
			if(count($data->player) > 0){
				if(!empty($data->player[0]->rank)){
					$data->award = $data->player[0]->award;
					$data->rank = $data->player[0]->rank;
				}
				else
					$this->get_award($data);
			}
			else
				$this->get_award($data);
		}
		$data->team_rank = $data->award;
		if(count($data->player) > 0)
			$data->seed_time = $data->player[0]->seed_time;

		$cutoff_group_member = CutoffGroupMember::where('registration_event_id', '=', $data->id)->orderBy('created_at', 'desc')->first();
		$data->cutoff_group_member = $cutoff_group_member;

		if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str)){
			$arr_coach = RegistrationEventCoach::where('registration_event_id', '=', $data->id)->get();
			foreach($arr_coach as $coach){
				$coach->coach_position;
				$coach->image_format = '<img src="'.(!empty($coach->file_name) ? url('/media/registration/coach?file_name='.$coach->file_name) : url('/image/no_image_available.jpeg')).'" style="width: 10rem;"/>';
				$coach->url_image = !empty($coach->file_name) ? url('/media/registration/coach?file_name='.$coach->file_name) : url('/image/no_image_available.jpeg');
			}
			$data->coach = $arr_coach;
		}
		$is_match_generated = !empty($data->event_category_sport_category) && (count($data->event_category_sport_category->group) > 0 || count($data->event_category_sport_category->cutoff_group) > 0 || count($data->event_category_sport_category->tournament) > 0);
		$data->is_match_generated = $is_match_generated;
		$data->is_match_not_generated = !$is_match_generated;


		$arr_player = RegistrationEventPlayer::where('registration_event_id', '=', $data->id)->get();
		foreach($arr_player as $player){
			$this->registration_event_player($player, $request);
			unset($player->registration_event);
		}
		$data->arr_player = $arr_player;
		$data->allow_dispensation = $data->type == 'live' && $data->status == 'success' && count($arr_player) > 0;

		$arr_coach = clone $data->coach;
		// foreach($arr_coach as $coach)
		// 	$this->registration_event_coach($coach);
		$data->arr_coach = $arr_coach;


		$data->venue;
		if(!empty($data->event_category_sport_category) && !empty($data->event_category_sport_category->event_category_sport)){
			$data->event_category_sport_category->event_category_sport->event;
			$data->event_category_sport_category->event_category_sport->category_sport;
			$data->event_category_sport_category->event_category_sport->scoring_type;

			$data->competition = $data->event_category_sport_category->event_category_sport;
			$data->competition->category_sport->image = !empty($data->competition->category_sport->file_name) ? url('/media/category-sport?file_name='.$data->competition->category_sport->file_name) : url('/image/no_image_available.jpeg');
		}
		$data->team_category = $data->event_category_sport_category;
		// $data->competition->category_sport = $data->event_category_sport_category->event_category_sport;
		$data->status_format1 = __('general.'.$data->status);
		$this->get_badge($data, 'status_format', __('general.'.$data->status), $data->status == 'success' ? 'primary' : ($data->status == 'wait_payment' ? 'warning' : 'danger'));

		$data->urgent_order = Order::where('registration_event_id', '=', $data->id)
			// ->where('status', '!=', 'canceled')
			->orderBy('status_level', 'desc')
			->orderBy('created_at', 'desc')
			->first();
		if(!empty($data->urgent_order))
			$data->urgent_order->detail;

		$match_event = MatchEvent::where('registration_event_id', '=', $data->id)
			->orderBy('created_at', 'desc')
			->first();
		$data->match_event = $match_event;


		unset($data->player);
		unset($data->coach);
		// unset($data->event_category_sport_category);
		// unset($data->venue);

		// $data->urgent_order = Order::where('registration_event_id', '=', $data->id)
		// 	// ->where('status', '!=', 'canceled')
		// 	->orderBy('status_level', 'desc')
		// 	->orderBy('created_at', 'desc')
		// 	->first();
		// if(!empty($data->urgent_order))
		// 	$data->urgent_order->detail;

		if(!empty($request->rel_type) && $request->rel_type == $this->request_simple_str){
			unset($data->cutoff_group_member);
			if(!empty($data->team_category) && !empty($data->event_category_sport_category)){
				unset($data->team_category->group);
				unset($data->team_category->cutoff_group);
				unset($data->event_category_sport_category->event_category_sport);
			}
		}

		if(!empty($request->rel_type2) && $request->rel_type2 == "simple2"){
			unset($data->event_category_sport_category);
			unset($data->competition);
			unset($data->team_category);
			unset($data->venue);
			unset($data->cutoff_group);
		}




		// foreach($data->player as $player)
		//   $player->order_detail->order;
		if(empty($request->rel_type) || (!empty($request->rel_type) && $request->rel_type != $this->request_simple_str)){
			$arr_order = $cache_helper->get_key(null, null, $request1, 'registration_order_'.$data->id, 3600, [ 'registration', 'order', $data->id, ]);
			if(!isset($arr_order)){
				$arr_order = Order::where('registration_event_id', '=', $data->id)->where('status', '!=', 'canceled')->get();
				foreach($arr_order as $order)
					$order->detail;

				$arr_order = $cache_helper->get_key($arr_order, null, $request1, 'registration_order_'.$data->id, 3600, [ 'registration', 'order', $data->id, ]);
			}
			$data->order = $arr_order;


			$temp = $cache_helper->get_key(null, null, $request1, 'registration_status_'.$data->id, 3600, [ 'registration', 'status', $data->id, ]);
			if(!isset($temp)){
				$order_temp = Order::select('id', 'status')
					->where($order_model->get_table_name().'.registration_event_id', '=', $data->id);

				$order_data = OrderDetail::select($order_detail_model->get_table_name().'.*')
					->joinSub($order_temp, $order_model->get_table_name(), $order_detail_model->get_table_name().'.order_id', '=', $order_model->get_table_name().'.id')
					->where(function($where) use($order_detail_model){
						$where = $where->orWhere($order_detail_model->get_table_name().'.detail', 'like', '%Registration%')
							->orWhere($order_detail_model->get_table_name().'.detail', 'like', '%Registration%Team%');
					})
					->orderBy('created_at', 'desc');

				$temp = clone $order_data;
				$temp = $temp->where($order_model->get_table_name().'.status', '=', 'success')->first();
				if(empty($temp)){
					$temp = clone $order_data;
					$temp = $temp->where($order_model->get_table_name().'.status', '=', 'wait_payment')->first();
				}

				if(empty($temp)){
					$temp = clone $order_data;
					$temp = $temp->where($order_model->get_table_name().'.status', '=', 'refunded')->first();
				}

				$temp = $cache_helper->get_key($temp, null, $request1, 'registration_status_'.$data->id, 3600, [ 'registration', 'status', $data->id, ]);
			}


			$data->status_registration = !empty($temp) ? $temp->order->status : 'canceled';
			$this->get_badge($data, 'status_registration_format', __('general.'.$data->status_registration), $data->status_registration == 'success' ? 'success' : ($data->status_registration == 'wait_payment' ? 'warning text-white' : 'danger'));

			if(!empty($request) && !empty($request->match_id)){
				$match_event = $cache_helper->get_key(null, null, $request1, 'registration_match_event_'.$data->id, 3600, [ 'registration', 'match_event', $data->id, ]);
				if(!isset($match_event)){
					$match_event = MatchEvent::where('match_id', '=', $request->match_id)
						->where('registration_event_id', '=', $data->id)
						->first();

					$match_event = $cache_helper->get_key($match_event, null, $request1, 'registration_match_event_'.$data->id, 3600, [ 'registration', 'match_event', $data->id, ]);
				}
				$data->match_event = $match_event;
			}


			if($data->event_category_sport_category->event_category_sport->scoring_type->data == 'league' || $data->event_category_sport_category->event_category_sport->scoring_type->data == 'tournament'){
				$status_match = $cache_helper->get_key(null, null, $request1, 'registration_status_match_'.$data->id, 3600, [ 'registration', 'status_match', $data->id, ]);
				if(!isset($status_match)){
					$total_match = 7;
					$arr_order_detail_data = OrderDetail::select($order_detail_model->get_table_name().'.*')
						->join($order_model->get_table_name(), $order_detail_model->get_table_name().'.order_id', '=', $order_model->get_table_name().'.id')
						->where(function($where) use($order_detail_model, $total_match) {
							for($x = 0; $x < $total_match; $x++)
								$where = $where->orWhere($order_detail_model->get_table_name().'.detail', 'like', 'Match Payment #'.($x + 1));
						})
						->where($order_model->get_table_name().'.registration_event_id', '=', $data->id)
						->whereNull($order_model->get_table_name().'.deleted_at')
						->orderBy('created_at', 'desc')
						->get();
					$counter = 0;
					foreach($arr_order_detail_data as $order_detail){
						if($order_detail->order->status != 'success')
							break;
						$counter++;
					}
					$status_match = count($arr_order_detail_data) == $total_match && $counter == $total_match ? 'match_paid' : 'match_unpaid';

					$status_match = $cache_helper->get_key($status_match, null, $request1, 'registration_status_match_'.$data->id, 3600, [ 'registration', 'status_match', $data->id, ]);
				}
				$data->status_match = $status_match;
				$this->get_badge($data, 'status_match_format', __('general.'.$data->status_match), $data->status_match == 'match_paid' ? 'primary' : 'danger');
			}
			else if($data->event_category_sport_category->event_category_sport->category_sport->type == 'sport' || $data->event_category_sport_category->event_category_sport->category_sport->type == 'fun_sport' || $data->event_category_sport_category->event_category_sport->category_sport->type == 'elite_sport'){
				$data->status_match = 'free';
				$this->get_badge($data, 'status_match_format', __('general.free'), 'success');
			}
			else if($data->event_category_sport_category->event_category_sport->category_sport->type == 'academic'){
				$order = $cache_helper->get_key(null, null, $request1, 'registration_order_'.$data->id, 3600, [ 'registration', 'order', $data->id, ]);
				$match = $cache_helper->get_key(null, null, $request1, 'registration_match_'.$data->id, 3600, [ 'registration', 'match', $data->id, ]);
				if(!isset($order)){
					$order = Order::where('registration_event_id', '=', $data->id)->orderBy('status_level', 'desc')->first();

					$cutoff_group_member = CutoffGroupMember::where('registration_event_id', '=', $data->id)->first();

					$tournament_temp = Tournament::select('id')
						->where('status', '=', 'on_progress')
						->where(function($where) use($tournament_model, $data) {
							$where = $where->where($tournament_model->get_table_name().'.event_category_sport_category_id', '=', $data->event_category_sport_category_id);
						})
						->where(function($where) use($data) {
							$where = $where->orWhere('registration_event1_id', '=', $data->id)
								->orWhere('registration_event2_id', '=', $data->id);
						});

					$cutoff_group_temp = CutoffGroup::select('id')
						->where(function($where) use($cutoff_group_model, $data) {
							$where = $where->where($cutoff_group_model->get_table_name().'.event_category_sport_category_id', '=', $data->event_category_sport_category_id);
						})
						->where(function($where) use($data, $cutoff_group_member) {
							if(!empty($cutoff_group_member))
								$where = $where->orWhere('id', '=', $cutoff_group_member->cutoff_group->id);
						});


					$match = MatchModel::select($match_model->get_table_name().'.*')
						->leftJoinSub($cutoff_group_temp, $cutoff_group_model->get_table_name(), function($join) use($match_model, $cutoff_group_model, $data){
							$join = $join->on($match_model->get_table_name().'.cutoff_group_id', '=', $cutoff_group_model->get_table_name().'.id');
						})
						->leftJoinSub($tournament_temp, $tournament_model->get_table_name(), function($join) use($match_model, $tournament_model, $data){
							$join = $join->on($match_model->get_table_name().'.tournament_id', '=', $tournament_model->get_table_name().'.id');
						})
						->where($match_model->get_table_name().'.status', '=', 'on_progress')
						// ->where(function($where) use($cutoff_group_model, $tournament_model, $cutoff_group_member, $tournament){
						// 	if(!empty($cutoff_group_member))
						// 		$where = $where->orWhere($cutoff_group_model->get_table_name().'.id', '=', $cutoff_group_member->cutoff_group->id);
						// 	if(!empty($tournament))
						// 		$where = $where->orWhere($tournament_model->get_table_name().'.id', '=', $tournament->id);
						// })

						// ->whereNull($cutoff_group_model->get_table_name().'.deleted_at')
						// ->whereNull($tournament_model->get_table_name().'.deleted_at')

						->orderBy('created_at', 'asc')
						->first();

					$order = $cache_helper->get_key($order, null, $request1, 'registration_order_'.$data->id, 3600, [ 'registration', 'order', $data->id, ]);
					$match = $cache_helper->get_key($match, null, $request1, 'registration_match_'.$data->id, 3600, [ 'registration', 'match', $data->id, ]);
				}

				if(!empty($match))
					$this->quiz_status($order, $data, $match);
			}

			foreach($data->match as $match)
				$this->registration_event_match($match);

			$data->expired_at_format = !empty($data->payment_expired_at) ? $data->payment_expired_at->isoFormat($this->date_format) : '-';
			$data->paid_at_format = !empty($data->paid_at) ? $data->paid_at->isoFormat($this->date_format) : 'Not Paid';

			$data->arr_match = $data->match;
			$data->arr_order = $data->order;

		}
	}
}
