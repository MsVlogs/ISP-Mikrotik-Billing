<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeamConveyanceClaim extends Model { protected $fillable=['user_id','claim_date','from_place','to_place','purpose','amount','note','status','reviewed_by']; protected $casts=['claim_date'=>'date','amount'=>'decimal:2']; public function user(){return $this->belongsTo(User::class);} public function reviewer(){return $this->belongsTo(User::class,'reviewed_by');} }
