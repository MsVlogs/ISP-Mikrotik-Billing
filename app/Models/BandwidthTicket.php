<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BandwidthTicket extends Model {
 protected $fillable=['bandwidth_reseller_id','ticket_no','subject','description','priority','status','assigned_to'];
 public function reseller(){return $this->belongsTo(BandwidthReseller::class,'bandwidth_reseller_id');}
 public static function nextNo(){return 'BW-TKT-'.str_pad((string)(static::max('id')+1),5,'0',STR_PAD_LEFT);}
}