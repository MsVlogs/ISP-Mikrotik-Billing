<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeamAttendanceSetting extends Model { protected $table='team_attendance_settings'; protected $fillable=['office_start','office_end','late_grace_minutes','location_mode','radius_mode','selfie_enabled','selfie_checkin','selfie_checkout','selfie_retention_days','weekly_off']; protected $casts=['selfie_enabled'=>'boolean','weekly_off'=>'array']; }
