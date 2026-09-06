<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeamPayrollRecord extends Model { protected $fillable=['user_id','month','base_salary','allowances','bonus','deductions','net_salary','status','note']; protected $casts=['month'=>'date','base_salary'=>'decimal:2','allowances'=>'decimal:2','bonus'=>'decimal:2','deductions'=>'decimal:2','net_salary'=>'decimal:2']; public function user(){return $this->belongsTo(User::class);} }
