<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("support_tickets", function (Blueprint $table) {
            $table->string("ticket_type")->default("complain")->after("customer_unique_id");
            $table->string("topic")->nullable()->after("category");
            $table->foreignId("assigned_to")->nullable()->after("topic")->constrained("users")->nullOnDelete();
            $table->boolean("notify_staff_bell")->default(true);
            $table->boolean("notify_staff_sms")->default(true);
            $table->boolean("notify_customer_sms")->default(true);
            $table->boolean("notify_customer_whatsapp")->default(false);
            $table->boolean("notify_owner_telegram")->default(false);
        });
        Schema::table("support_tickets", function (Blueprint $table) {
            $table->string("priority")->default("medium")->change();
            $table->string("status")->default("new")->change();
        });

        Schema::create("sales_queries", function (Blueprint $table) {
            $table->id(); $table->string("prospect_name"); $table->string("email")->nullable();
            $table->string("mobile1"); $table->string("mobile2")->nullable(); $table->string("nid")->nullable();
            $table->string("connection_type"); $table->unsignedBigInteger("package_id")->nullable(); $table->date("expected_date")->nullable();
            $table->string("lead_source")->nullable(); $table->string("referred_by")->nullable(); $table->string("priority")->default("low");
            $table->timestamp("follow_up_at")->nullable(); $table->string("floor_flat")->nullable(); $table->string("house")->nullable();
            $table->string("road")->nullable(); $table->string("area_id")->nullable(); $table->string("area_text")->nullable();
            $table->string("district")->nullable(); $table->string("thana")->nullable(); $table->foreignId("assigned_to")->nullable()->constrained("users")->nullOnDelete();
            $table->text("remarks")->nullable(); $table->string("status")->default("new"); $table->timestamps();
            $table->foreign("package_id")->references("id")->on("package_lists")->nullOnDelete();
        });

        Schema::create("kyc_requests", function (Blueprint $table) {
            $table->id(); $table->string("customer_unique_id")->index(); $table->string("customer_name")->nullable();
            $table->string("phone")->nullable(); $table->string("nid")->nullable(); $table->string("email")->nullable();
            $table->string("document_type")->nullable(); $table->string("document_reference")->nullable(); $table->text("notes")->nullable();
            $table->string("status")->default("pending"); $table->foreignId("reviewed_by")->nullable()->constrained("users")->nullOnDelete(); $table->timestamp("reviewed_at")->nullable(); $table->timestamps();
        });

        Schema::create("support_ticket_templates", function (Blueprint $table) {
            $table->id(); $table->string("type"); $table->string("name"); $table->unsignedInteger("sort_order")->default(0);
            $table->string("subject_template")->nullable(); $table->text("internal_note_template")->nullable(); $table->text("description_template")->nullable();
            $table->text("customer_message")->nullable(); $table->text("staff_message")->nullable();
            $table->boolean("bell_notification")->default(true); $table->boolean("staff_sms")->default(true); $table->boolean("customer_sms")->default(true);
            $table->boolean("customer_whatsapp")->default(false); $table->boolean("owner_telegram")->default(false); $table->boolean("custom_override")->default(false); $table->boolean("active")->default(true); $table->timestamps();
            $table->unique(["type","name"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("support_ticket_templates"); Schema::dropIfExists("kyc_requests"); Schema::dropIfExists("sales_queries");
        Schema::table("support_tickets", function (Blueprint $table) {
            $table->dropForeign(["assigned_to"]); $table->dropColumn(["ticket_type","topic","assigned_to","notify_staff_bell","notify_staff_sms","notify_customer_sms","notify_customer_whatsapp","notify_owner_telegram"]);
        });
    }
};
