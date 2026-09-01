<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BandwidthReseller extends Model {
 protected $fillable=['code','company','contact_name','email','phone','username','monthly_due','credit_balance','billing_day','status','notes'];
 protected $casts=['monthly_due'=>'decimal:2','credit_balance'=>'decimal:2'];
 public function services(){return $this->hasMany(BandwidthService::class);}
 public function invoices(){return $this->hasMany(BandwidthInvoice::class);}
 public function tickets(){return $this->hasMany(BandwidthTicket::class);}
 public function getOutstandingAttribute(){return max(0,(float)$this->invoices()->sum('total_amount')-(float)$this->invoices()->sum('paid_amount'));}
}