<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeamAttendanceRecord extends Model { protected $fillable=['user_id','attendance_date','check_in_at','check_out_at','check_in_lat','check_in_lng','check_out_lat','check_out_lng','status','note']; protected $casts=['attendance_date'=>'date','check_in_at'=>'datetime','check_out_at'=>'datetime']; public function user(){return $this->belongsTo(User::class);} }
