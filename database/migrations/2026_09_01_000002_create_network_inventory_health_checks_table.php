<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('network_inventory_health_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('network_inventory_device_id');
            $table->string('status', 16);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('checked_at');
            $table->index(['network_inventory_device_id', 'checked_at'], 'ni_health_device_checked_idx');
            $table->index(['status', 'checked_at'], 'ni_health_status_checked_idx');
            $table->foreign('network_inventory_device_id', 'ni_health_device_fk')
                ->references('id')->on('network_inventory_devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_inventory_health_checks');
    }
};
