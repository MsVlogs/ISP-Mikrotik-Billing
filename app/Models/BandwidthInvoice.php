<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BandwidthInvoice extends Model {
 protected $fillable=['bandwidth_reseller_id','invoice_no','billing_month','due_date','total_amount','paid_amount','status','notes'];
 protected $casts=['billing_month'=>'date','due_date'=>'date','total_amount'=>'decimal:2','paid_amount'=>'decimal:2'];
 public function reseller(){return $this->belongsTo(BandwidthReseller::class,'bandwidth_reseller_id');}
 public function getBalanceAttribute(){return max(0,(float)$this->total_amount-(float)$this->paid_amount);}
}