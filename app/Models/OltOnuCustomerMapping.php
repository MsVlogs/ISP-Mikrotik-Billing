<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OltOnuCustomerMapping extends Model {
 protected $fillable=['olt_device_id','customer_id','ppp_user_id','onu_id','onu_serial','onu_mac','pon_port','onu_type','status','rx_power','tx_power','onu_ip','last_seen_at','notes'];
 protected $casts=['last_seen_at'=>'datetime','rx_power'=>'decimal:2','tx_power'=>'decimal:2'];
 public function olt(){return $this->belongsTo(NetworkInventoryDevice::class,'olt_device_id');}
 public function customer(){return $this->belongsTo(CustomersInfo::class,'customer_id');}
 public function pppUser(){return $this->belongsTo(PPPSecrets::class,'ppp_user_id');}
}
