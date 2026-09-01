<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('network_inventory_devices', function (Blueprint $table) {
            foreach ([
                'olt_type_id','host','firmware','serial_no','web_protocol','diagnostic_command','adapter_config'
            ] as $column) {
                if (!Schema::hasColumn('network_inventory_devices', $column)) $table->text($column)->nullable();
            }
            foreach ([
                'port','pon_ports','ge_ports','sfp_ports','sfp_plus_ports','web_port','connect_timeout','cli_timeout','read_delay_ms'
            ] as $column) {
                if (!Schema::hasColumn('network_inventory_devices', $column)) $table->unsignedInteger($column)->nullable();
            }
            if (!Schema::hasColumn('network_inventory_devices', 'is_active')) $table->boolean('is_active')->default(true);
        });
    }
    public function down(): void {
        Schema::table('network_inventory_devices', function (Blueprint $table) {
            foreach ([
                'olt_type_id','host','firmware','serial_no','web_protocol','diagnostic_command','adapter_config',
                'port','pon_ports','ge_ports','sfp_ports','sfp_plus_ports','web_port','connect_timeout','cli_timeout','read_delay_ms','is_active'
            ] as $column) if (Schema::hasColumn('network_inventory_devices',$column)) $table->dropColumn($column);
        });
    }
};
