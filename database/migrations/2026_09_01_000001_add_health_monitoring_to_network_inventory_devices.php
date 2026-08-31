<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('network_inventory_devices', function (Blueprint $table) {
            $table->unsignedSmallInteger('health_port')->nullable()->after('ip_address');
            $table->boolean('monitor_enabled')->default(false)->after('health_port');
            $table->string('health_status', 16)->default('unknown')->after('monitor_enabled');
            $table->unsignedInteger('last_latency_ms')->nullable()->after('health_status');
            $table->timestamp('last_checked_at')->nullable()->after('last_latency_ms');
            $table->index(['monitor_enabled', 'health_status']);
        });
    }

    public function down(): void
    {
        Schema::table('network_inventory_devices', function (Blueprint $table) {
            $table->dropIndex(['monitor_enabled', 'health_status']);
            $table->dropColumn(['health_port', 'monitor_enabled', 'health_status', 'last_latency_ms', 'last_checked_at']);
        });
    }
};
