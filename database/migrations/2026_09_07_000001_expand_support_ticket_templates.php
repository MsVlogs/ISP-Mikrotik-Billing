<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ticket_templates', function (Blueprint $table) {
            $table->text('body_template')->nullable()->after('description_template');
            $table->text('customer_message_template')->nullable()->after('customer_message');
            $table->text('staff_message_template')->nullable()->after('staff_message');
            $table->boolean('allow_custom_channels')->default(false)->after('custom_override');
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_templates', function (Blueprint $table) {
            $table->dropColumn(['body_template','customer_message_template','staff_message_template','allow_custom_channels']);
        });
    }
};
