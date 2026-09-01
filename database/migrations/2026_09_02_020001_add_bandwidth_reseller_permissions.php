<?php
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
return new class extends Migration {
 public function up(): void {foreach(['bandwidth-reseller','bandwidth-services','bandwidth-billing','bandwidth-support'] as $p){Permission::firstOrCreate(['name'=>$p,'guard_name'=>'web']);}}
 public function down(): void {Permission::whereIn('name',['bandwidth-reseller','bandwidth-services','bandwidth-billing','bandwidth-support'])->delete();}
};