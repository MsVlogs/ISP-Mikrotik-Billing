<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeamAttendanceDevice extends Model { protected $fillable=['device_name','device_type','device_ip','device_port','device_location','enabled']; protected $casts=['enabled'=>'boolean']; }
