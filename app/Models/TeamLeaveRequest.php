<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeamLeaveRequest extends Model { protected $fillable=['user_id','leave_type','from_date','to_date','reason','status','approved_by']; protected $casts=['from_date'=>'date','to_date'=>'date']; public function user(){return $this->belongsTo(User::class);} public function approver(){return $this->belongsTo(User::class,'approved_by');} }
