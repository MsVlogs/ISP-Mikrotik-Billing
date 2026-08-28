<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['label' => 'Present Address', 'input_type' => 'textarea', 'order' => 10],
            ['label' => 'Zone Name', 'input_type' => 'text', 'order' => 11],
            ['label' => 'Network Location', 'input_type' => 'text', 'order' => 12],
        ] as $field) {
            DB::table('address_fields')->updateOrInsert(
                ['label' => $field['label']],
                [
                    'input_type' => $field['input_type'],
                    'dropdown_list' => null,
                    'required' => false,
                    'print_preview' => false,
                    'complain_preview' => false,
                    'order' => $field['order'],
                    'receipt_order' => $field['order'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('address_fields')
            ->whereIn('label', ['Present Address', 'Zone Name', 'Network Location'])
            ->delete();
    }
};
