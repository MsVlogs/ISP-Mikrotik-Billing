<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeamAttendanceLocation extends Model { protected $fillable=['location_name','lat','lng','radius_m','enabled']; protected $casts=['enabled'=>'boolean']; }
