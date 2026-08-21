<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeviceWatcher extends Model
{ protected $fillable=['name','router_name','host','port','interval_seconds','threshold_ms','enabled','last_status','last_latency_ms','last_checked_at']; protected $casts=['enabled'=>'boolean','last_checked_at'=>'datetime']; }
