<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('device_watchers',function(Blueprint $t){$t->id();$t->string('name');$t->string('router_name')->nullable();$t->string('host');$t->unsignedInteger('port')->default(8728);$t->unsignedInteger('interval_seconds')->default(60);$t->unsignedInteger('threshold_ms')->default(1000);$t->boolean('enabled')->default(true);$t->string('last_status')->nullable();$t->unsignedInteger('last_latency_ms')->nullable();$t->timestamp('last_checked_at')->nullable();$t->timestamps();}); } public function down(): void { Schema::dropIfExists('device_watchers'); } };
