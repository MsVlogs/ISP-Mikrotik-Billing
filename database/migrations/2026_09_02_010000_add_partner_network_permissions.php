<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['partner-network', 'reseller-cashflow', 'reseller-ledger'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', ['partner-network', 'reseller-cashflow', 'reseller-ledger'])
            ->where('guard_name', 'web')->delete();
    }
};
