<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeamVisit extends Model { protected $fillable=['user_id','customer_id','visit_type','visited_at','lat','lng','purpose','note']; protected $casts=['visited_at'=>'datetime']; public function user(){return $this->belongsTo(User::class);} public function customer(){return $this->belongsTo(CustomersInfo::class);} }
