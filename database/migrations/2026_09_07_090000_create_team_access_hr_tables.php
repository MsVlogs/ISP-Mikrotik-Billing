<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_attendance_records', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date'); $table->timestamp('check_in_at')->nullable(); $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_in_lat',10,7)->nullable(); $table->decimal('check_in_lng',10,7)->nullable();
            $table->decimal('check_out_lat',10,7)->nullable(); $table->decimal('check_out_lng',10,7)->nullable();
            $table->string('status')->default('present'); $table->text('note')->nullable(); $table->timestamps();
            $table->unique(['user_id','attendance_date']);
        });
        Schema::create('team_leave_requests', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('leave_type');
            $table->date('from_date'); $table->date('to_date'); $table->text('reason')->nullable(); $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->index(['status','from_date']);
        });
        Schema::create('team_payroll_records', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->date('month');
            $table->decimal('base_salary',12,2)->default(0); $table->decimal('allowances',12,2)->default(0); $table->decimal('bonus',12,2)->default(0);
            $table->decimal('deductions',12,2)->default(0); $table->decimal('net_salary',12,2)->default(0); $table->string('status')->default('draft'); $table->text('note')->nullable(); $table->timestamps();
            $table->unique(['user_id','month']);
        });
        Schema::create('team_visits', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers_infos')->nullOnDelete(); $table->string('visit_type');
            $table->timestamp('visited_at'); $table->decimal('lat',10,7)->nullable(); $table->decimal('lng',10,7)->nullable();
            $table->string('purpose')->nullable(); $table->text('note')->nullable(); $table->timestamps();
        });
        Schema::create('team_conveyance_claims', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->date('claim_date');
            $table->string('from_place'); $table->string('to_place'); $table->string('purpose'); $table->decimal('amount',12,2);
            $table->text('note')->nullable(); $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->index(['status','claim_date']);
        });
        Schema::create('team_attendance_settings', function (Blueprint $table) {
            $table->id(); $table->time('office_start')->default('09:00:00'); $table->time('office_end')->default('18:00:00');
            $table->unsignedSmallInteger('late_grace_minutes')->default(15); $table->string('location_mode')->default('record');
            $table->string('radius_mode')->default('record'); $table->boolean('selfie_enabled')->default(false);
            $table->string('selfie_checkin')->default('optional'); $table->string('selfie_checkout')->default('optional');
            $table->unsignedSmallInteger('selfie_retention_days')->default(30); $table->json('weekly_off')->nullable(); $table->timestamps();
        });
        Schema::create('team_attendance_devices', function (Blueprint $table) {
            $table->id(); $table->string('device_name'); $table->string('device_type'); $table->string('device_ip'); $table->unsignedInteger('device_port')->default(4370);
            $table->string('device_location')->nullable(); $table->boolean('enabled')->default(true); $table->timestamps();
        });
        Schema::create('team_attendance_locations', function (Blueprint $table) {
            $table->id(); $table->string('location_name'); $table->decimal('lat',10,7); $table->decimal('lng',10,7);
            $table->unsignedInteger('radius_m')->default(100); $table->boolean('enabled')->default(true); $table->timestamps();
        });
        DB::table('team_attendance_settings')->insert(['id'=>1,'office_start'=>'09:00:00','office_end'=>'18:00:00','late_grace_minutes'=>15,'weekly_off'=>json_encode(['Friday'])]);
    }

    public function down(): void
    {
        Schema::dropIfExists('team_attendance_locations'); Schema::dropIfExists('team_attendance_devices');
        Schema::dropIfExists('team_attendance_settings'); Schema::dropIfExists('team_conveyance_claims');
        Schema::dropIfExists('team_visits'); Schema::dropIfExists('team_payroll_records');
        Schema::dropIfExists('team_leave_requests'); Schema::dropIfExists('team_attendance_records');
    }
};
