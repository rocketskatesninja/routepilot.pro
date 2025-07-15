<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('street_address')->nullable();
            $table->string('street_address_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->text('notes_by_client')->nullable();
            $table->text('notes_by_admin')->nullable();
            $table->string('profile_photo')->nullable();
            $table->enum('role', ['client', 'tech', 'admin'])->default('client');
            $table->boolean('appointment_reminders')->default(true);
            $table->boolean('mailing_list')->default(true);
            $table->boolean('monthly_billing')->default(true);
            $table->enum('service_reports', ['full', 'invoice_only', 'none'])->default('full');
            $table->enum('status', ['active', 'inactive', 'pending'])->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
