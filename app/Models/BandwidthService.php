<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BandwidthService extends Model {
 protected $fillable=['bandwidth_reseller_id','name','service_type','monthly_charge','status','notes'];
 protected $casts=['monthly_charge'=>'decimal:2'];
 public function reseller(){return $this->belongsTo(BandwidthReseller::class,'bandwidth_reseller_id');}
}