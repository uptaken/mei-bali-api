<?php
namespace App\Http\Controllers\Helper;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Auth;

use App\Models\Announcement;
use App\Models\AcademicYear;
use App\Models\User;

class SidebarHelper{
  private $arr = [
    [
      "id" => 'dashboard',
      "name" => 'general.dashboard',
      "icon" => 'fa-tachometer-alt',
      "href" => '/',
      "url" => '/',
    ],
    
    [
      "id" => 'membership',
      "name" => 'general.membership',
      "icon" => 'fa-tachometer-alt',
      "for_label" => true,
      "href" => '#membership',
      "url" => 'membership',
      "roles" => ["super_holding",],
    ],
    [
      "id" => 'member',
      "name" => 'general.level_membership',
      "icon" => 'fa-tachometer-alt',
      "href" => '/member',
      "url" => 'member',
      "roles" => ["super_holding",],
    ],
    [
      "id" => 'setting_xp',
      "name" => 'general.setting_xp',
      "icon" => 'fa-tachometer-alt',
      "href" => '/setting/xp',
      "url" => 'setting/xp',
      "roles" => ["super_holding",],
    ],
    
    
    [
      "id" => 'top_up_point',
      "name" => 'general.top_up_point',
      "icon" => 'fa-tachometer-alt',
      "for_label" => true,
      "href" => '#top_up_point',
      "url" => 'top_up_point',
      "roles" => ["super_holding",],
    ],
    [
      "id" => 'point_package',
      "name" => 'general.top_up_package',
      "icon" => 'fa-tachometer-alt',
      "href" => '/package/point',
      "url" => 'package/point',
      "roles" => ["super_holding",],
    ],
    [
      "id" => 'bonus_point',
      "name" => 'general.bonus_point',
      "icon" => 'fa-tachometer-alt',
      "href" => '/bonus/point',
      "url" => 'bonus/point',
      "roles" => ["super_holding",],
    ],
    [
      "id" => 'setting_point',
      "name" => 'general.setting_point',
      "icon" => 'fa-tachometer-alt',
      "href" => '/setting/point',
      "url" => 'setting/point',
      "roles" => ["super_holding",],
    ],
    


    [
      "id" => 'transaction',
      "name" => 'general.transaction',
      "icon" => 'fa-tachometer-alt',
      "for_label" => true,
      "href" => '#transaction',
      "url" => 'transaction',
      "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto",],
    ],
    [
      "id" => 'pay_bill',
      "name" => 'general.pay_bill',
      "icon" => 'fa-tachometer-alt',
      "href" => '/pay-bill',
      "url" => 'pay-bill',
      "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto",],
    ],
    [
      "id" => 'point_transaction',
      "name" => 'general.top_up_transaction',
      "icon" => 'fa-tachometer-alt',
      "href" => '/point-transaction',
      "url" => 'point-transaction',
      "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto",],
    ],
    [
      "id" => 'transfer_transaction',
      "name" => 'general.transfer_transaction',
      "icon" => 'fa-tachometer-alt',
      "href" => '/transfer-transaction',
      "url" => 'transfer-transaction',
      "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto",],
    ],
    [
      "id" => 'reservation_event',
      "name" => 'general.event_booking',
      "icon" => 'fa-tachometer-alt',
      "href" => '/reservation/event?type=event_app_only',
      "url" => 'reservation/event',
      "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto",],
    ],
    [
      "id" => 'reservation_table',
      "name" => 'general.table_booking',
      "icon" => 'fa-tachometer-alt',
      "href" => '/reservation/table',
      "url" => 'reservation/table',
      "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto",],
    ],


    [
      "id" => 'other',
      "name" => 'general.other',
      "icon" => 'fa-tachometer-alt',
      "for_label" => true,
      "href" => '#other',
      "url" => 'other',
      "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto", "marketing",],
    ],
    [
      "id" => 'master',
      "name" => 'general.master',
      "icon" => 'fa-tachometer-alt',
      "href" => '#master',
      "url" => 'master',
      "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto", "marketing",],
      "arr" => [
        [
          "id" => 'voucher',
          "name" => 'general.voucher',
          "icon" => 'fa-tachometer-alt',
          "href" => '/master/voucher',
          "url" => 'master/voucher',
          "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto",],
        ],
        [
          "id" => 'event',
          "name" => 'general.event',
          "icon" => 'fa-tachometer-alt',
          "href" => '/master/event',
          "url" => 'master/event',
          "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto", "marketing",],
        ],
        [
          "id" => 'outlet',
          "name" => 'general.list_resto',
          "icon" => 'fa-tachometer-alt',
          "href" => '/master/outlet',
          "url" => 'master/outlet',
          "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto", "marketing",],
        ],
        [
          "id" => 'avatar',
          "name" => 'general.avatar',
          "icon" => 'fa-tachometer-alt',
          "href" => '/master/avatar',
          "url" => 'master/avatar',
          "roles" => ["super_holding",],
        ],
        [
          "id" => 'term_condition',
          "name" => 'general.term_condition',
          "icon" => 'fa-tachometer-alt',
          "href" => '/master/term-condition',
          "url" => 'master/term-condition',
          "roles" => ["super_holding",],
        ],
        [
          "id" => 'contact_us',
          "name" => 'general.contact_us',
          "icon" => 'fa-tachometer-alt',
          "href" => '/master/contact-us',
          "url" => 'master/contact-us',
          "roles" => ["super_holding",],
        ],
        [
          "id" => 'about_us',
          "name" => 'general.about_us',
          "icon" => 'fa-tachometer-alt',
          "href" => '/master/about-us',
          "url" => 'master/about-us',
          "roles" => ["super_holding",],
        ],
      ],
    ],
    [
      "id" => 'notification',
      "name" => 'general.notification',
      "icon" => 'fa-tachometer-alt',
      "href" => '/notification',
      "url" => 'notification',
      "roles" => ["super_holding",],
    ],
    [
      "id" => 'report',
      "name" => 'general.report',
      "icon" => 'fa-tachometer-alt',
      "href" => '#report',
      "url" => 'report',
      "roles" => ["super_holding", "accounting", "super_admin_resto", "admin_cabang_resto",],
      "arr" => [
        [
          "id" => 'top_up',
          "name" => 'general.top_up',
          "icon" => 'fa-tachometer-alt',
          "href" => '/report/top-up',
          "url" => 'report/top-up',
          "roles" => ["super_holding", "accounting", "super_admin_resto", "admin_cabang_resto",],
        ],
        [
          "id" => 'event',
          "name" => 'general.event',
          "icon" => 'fa-tachometer-alt',
          "href" => '/report/event',
          "url" => 'report/event',
          "roles" => ["super_holding", "accounting", "super_admin_resto", "admin_cabang_resto",],
        ],
        [
          "id" => 'table_reservation',
          "name" => 'general.table_reservation',
          "icon" => 'fa-tachometer-alt',
          "href" => '/report/table-reservation',
          "url" => 'report/table-reservation',
          "roles" => ["super_holding", "accounting", "super_admin_resto", "admin_cabang_resto",],
        ],
        [
          "id" => 'bill',
          "name" => 'general.bill',
          "icon" => 'fa-tachometer-alt',
          "href" => '/report/bill',
          "url" => 'report/bill',
          "roles" => ["super_holding", "accounting", "super_admin_resto", "admin_cabang_resto",],
        ],
      ],
    ],
    [
      "id" => 'user',
      "name" => 'general.user',
      "icon" => 'fa-tachometer-alt',
      "href" => '#user',
      "url" => 'user',
      "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto", "accounting",],
      "arr" => [
        [
          "id" => 'cashier',
          "name" => 'general.cashier',
          "icon" => 'fa-tachometer-alt',
          "href" => '/user/cashier',
          "url" => 'user/cashier',
          "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto", ],
        ],
        [
          "id" => 'customer',
          "name" => 'general.customer',
          "icon" => 'fa-tachometer-alt',
          "href" => '/user/customer',
          "url" => 'user/customer',
          "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto", "accounting",],
        ],
        [
          "id" => 'accounting',
          "name" => 'general.accounting',
          "icon" => 'fa-tachometer-alt',
          "href" => '/user/accounting',
          "url" => 'user/accounting',
          "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto",],
        ],
        [
          "id" => 'marketing',
          "name" => 'general.marketing',
          "icon" => 'fa-tachometer-alt',
          "href" => '/user/marketing',
          "url" => 'user/marketing',
          "roles" => ["super_holding", "super_admin_resto", "admin_cabang_resto",],
        ],
        [
          "id" => 'super_admin_outlet',
          "name" => 'general.super_admin_outlet',
          "icon" => 'fa-tachometer-alt',
          "href" => '/user/admin-outlet',
          "url" => 'user/admin-outlet',
          "roles" => ["super_holding",],
        ],
        [
          "id" => 'admin_branch',
          "name" => 'general.admin_branch',
          "icon" => 'fa-tachometer-alt',
          "href" => '/user/admin-branch',
          "url" => 'user/admin-branch',
          "roles" => ["super_holding", "super_admin_resto",],
        ],
      ],
    ],
  ];

  public function manage_href($arr){
    foreach($arr as $key => $data){
      if(!empty($data["arr"]))
        $arr[$key]["arr"] = $this->manage_href($data["arr"]);
      else
        $arr[$key]["href"] = url($data["href"]);
    }
    return $arr;
  }

  public function manage_role($arr){
    $arr_temp = [];
    foreach($arr as $key => $data){
      $allow_roles = false;
      if(Auth::check()){
        if(!empty($data["roles"])){
          foreach($data["roles"] as $roles){
            if($roles == Auth::user()->type->name){
              $allow_roles = true;
              break;
            }
          }
        }
        else
          $allow_roles = true;
      }

      if(!$allow_roles)
        continue;
      else{
        if(!empty($data["arr"]))
          $data["arr"] = $this->manage_role($data["arr"]);
        array_push($arr_temp, $data);
      }
      
    }
    
    
    return $arr_temp;
  }

  public function get_arr_sidebar($request){
    $arr = $this->arr;
    $arr = $this->manage_href($arr);
    $arr = $this->manage_role($arr);

    // foreach($arr as $key => $data){
    //   if(!empty($data['arr'])){
    //     foreach($data['arr'] as $key1 => $data1){
    //       if($data1['id'] == 'user_operator' && Auth::user()->type->name == 'operator'){
    //         array_splice($data, $key1, 1);
    //         break;
    //       }
    //     }
    //   }
    // }

    return [
      'arr_sidebar' => $arr,
      'json_arr_sidebar' => json_encode($arr),
    ];
  }
}
