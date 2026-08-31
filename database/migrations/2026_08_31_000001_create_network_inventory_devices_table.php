<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('network_inventory_devices', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('name');
            $table->string('ip_address')->nullable();
            $table->string('vendor')->nullable();
            $table->string('model')->nullable();
            $table->string('status', 16)->default('offline');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['type', 'status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('network_inventory_devices');
    }
};
