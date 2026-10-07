<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Auth;

use App\Http\Controllers\Helper\FileHelper;
use App\Http\Controllers\BaseController;

use App\Models\EndpointLog;

class Base extends Model
{
    use SoftDeletes;
    // configuration for increment id
    public $incrementing = false;

    public $arr_relationship = [];
    public $arr_column_image = [];

    public $from_system = false;

    protected $casts = [
      'date' => 'datetime',
      'paid_at' => 'datetime',
      'payment_expired_at' => 'datetime',
      'start_date' => 'datetime',
      'end_date' => 'datetime',
      'birth_date' => 'datetime',
      'publish_at' => 'datetime',
      'phone_verified_at' => 'datetime',
      'school_registration' => 'datetime',
      'registration_team' => 'datetime',
      'payment_registration' => 'datetime',
      'coach_meeting' => 'datetime',
      'medal_ceremony' => 'datetime',
      'start_payment_registration' => 'datetime',
      'end_payment_registration' => 'datetime',
      'start_registration_team' => 'datetime',
      'end_registration_team' => 'datetime',
      'start_school_registration' => 'datetime',
      'end_school_registration' => 'datetime',
      'redeemed_at' => 'datetime',
    ];

    public static function boot() {
      parent::boot();

      static::deleting(function($data) {
        if(Auth::check()){
          $data->deleted_by = Auth::user()->id;
          $data->save();
        }
      });

      static::deleted(function($data) {
        $data->on_delete_relationship($data);
      });

      static::creating(function($data) {
        $data->id = parent::get_id($data);
        if(Auth::check()){
          $data->created_by = Auth::user()->id;
          $data->updated_by = Auth::user()->id;
        }
      });

      static::updating(function($data)  {
        if(Auth::check()){
          $data->updated_by = Auth::user()->id;
        }
      });

      static::restoring(function ($data) {
        $data->deleted_by = null;
        $data->save();
        
        // $data->on_restore_relationship($data);
      });
    }

    public function on_delete_relationship($data){
      $file_helper = new FileHelper();
      foreach($this->arr_column_image as $key => $column){
        if(!empty($data->{$key}))
          $file_helper->remove_image($column, $data->{$key});
      }

      foreach($this->arr_relationship as $relationship){
        foreach($data->{$relationship} as $rel)
          $rel->delete();
      }
    }
    
    public function on_restore_relationship($data){
      $file_helper = new FileHelper();
    
      foreach($this->arr_relationship as $relationship){
        foreach($data->{$relationship}->withTrashed() as $rel){
          $rel->deleted_by = null;
          $rel->save();
          $rel->restore();
        }
      }
    }

    // function to show table name
    public function get_table_name(){
        return $this->table;
    }

    public function get_id($data){
      $base_controller = new BaseController();
        return $base_controller->id_helper->generate_new_id_with_date($this->get_id_label(), $data);
    }

    public function get_id_label(){
      return strtoupper($this->table);
    }
		
		public function get_non_id_label($id){
			return substr($id, strlen($this->table) + 1);
			
		}

    public function created_user(){
      return $this->belongsTo('App\Models\User', 'created_by', 'id');
    }

    public function updated_user(){
      return $this->belongsTo('App\Models\User', 'updated_by', 'id');
    }
}
