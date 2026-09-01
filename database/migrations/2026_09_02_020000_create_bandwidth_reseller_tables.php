<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('bandwidth_resellers',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('company');$t->string('contact_name');$t->string('email')->nullable();$t->string('phone');$t->string('username')->nullable();$t->decimal('monthly_due',15,2)->default(0);$t->decimal('credit_balance',15,2)->default(0);$t->unsignedTinyInteger('billing_day')->default(10);$t->enum('status',['active','suspended'])->default('active');$t->text('notes')->nullable();$t->timestamps();});
  Schema::create('bandwidth_services',function(Blueprint $t){$t->id();$t->foreignId('bandwidth_reseller_id')->constrained()->cascadeOnDelete();$t->string('name');$t->string('service_type');$t->decimal('monthly_charge',15,2)->default(0);$t->enum('status',['active','disabled'])->default('active');$t->text('notes')->nullable();$t->timestamps();});
  Schema::create('bandwidth_invoices',function(Blueprint $t){$t->id();$t->foreignId('bandwidth_reseller_id')->constrained()->cascadeOnDelete();$t->string('invoice_no')->unique();$t->date('billing_month');$t->date('due_date');$t->decimal('total_amount',15,2)->default(0);$t->decimal('paid_amount',15,2)->default(0);$t->enum('status',['outstanding','unpaid','partial','paid','cancelled'])->default('unpaid');$t->text('notes')->nullable();$t->timestamps();});
  Schema::create('bandwidth_tickets',function(Blueprint $t){$t->id();$t->foreignId('bandwidth_reseller_id')->constrained()->cascadeOnDelete();$t->string('ticket_no')->unique();$t->string('subject');$t->text('description');$t->enum('priority',['low','normal','high','urgent'])->default('normal');$t->enum('status',['new','open','pending','closed'])->default('new');$t->string('assigned_to')->nullable();$t->timestamps();});
 }
 public function down(): void {Schema::dropIfExists('bandwidth_tickets');Schema::dropIfExists('bandwidth_invoices');Schema::dropIfExists('bandwidth_services');Schema::dropIfExists('bandwidth_resellers');}
};