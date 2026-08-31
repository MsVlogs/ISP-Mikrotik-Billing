<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('network_inventory_devices', function (Blueprint $table) {
            $table->unsignedInteger('onu_total')->nullable()->after('model');
            $table->unsignedInteger('onu_online')->nullable()->after('onu_total');
            $table->decimal('rx_power', 6, 2)->nullable()->after('onu_online');
            $table->unsignedInteger('customer_count')->nullable()->after('rx_power');
        });
    }

    public function down(): void
    {
        Schema::table('network_inventory_devices', function (Blueprint $table) {
            $table->dropColumn(['onu_total', 'onu_online', 'rx_power', 'customer_count']);
        });
    }
};
