<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    /**
     * Keep Customer IDs unique among live customers, while freeing the ID
     * when the previous customer is soft-deleted.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite', 'pgsql'], true)) {
            throw new RuntimeException("Customer CID reuse is not configured for database driver [{$driver}].");
        }

        Schema::table('customers_infos', function (Blueprint $table): void {
            $table->dropUnique('customers_infos_customer_unique_id_unique');
        });

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // Active rows expose their CID through a generated column.
            // Soft-deleted rows expose NULL, which allows a later customer
            // to reuse the same CID while retaining the old record/history.
            DB::statement(
                'ALTER TABLE customers_infos
                 ADD COLUMN active_customer_unique_id VARCHAR(255)
                 GENERATED ALWAYS AS (
                     CASE WHEN deleted_at IS NULL THEN customer_unique_id ELSE NULL END
                 ) STORED'
            );

            Schema::table('customers_infos', function (Blueprint $table): void {
                $table->unique(
                    'active_customer_unique_id',
                    'customers_infos_active_cid_unique'
                );
            });

            return;
        }

        // SQLite and PostgreSQL support partial unique indexes directly.
        DB::statement(
            'CREATE UNIQUE INDEX customers_infos_active_cid_unique
             ON customers_infos (customer_unique_id)
             WHERE deleted_at IS NULL'
        );
    }

    /**
     * Restore the original all-record uniqueness only when no CID has
     * already been reused across a deleted and a live customer.
     */
    public function down(): void
    {
        $hasReusedCid = DB::table('customers_infos')
            ->select('customer_unique_id')
            ->groupBy('customer_unique_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasReusedCid) {
            throw new RuntimeException(
                'Cannot roll back CID reuse: at least one Customer ID exists in multiple historical/live records.'
            );
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            Schema::table('customers_infos', function (Blueprint $table): void {
                $table->dropUnique('customers_infos_active_cid_unique');
            });

            DB::statement('ALTER TABLE customers_infos DROP COLUMN active_customer_unique_id');
        } else {
            DB::statement('DROP INDEX IF EXISTS customers_infos_active_cid_unique');
        }

        Schema::table('customers_infos', function (Blueprint $table): void {
            $table->unique('customer_unique_id', 'customers_infos_customer_unique_id_unique');
        });
    }
};
