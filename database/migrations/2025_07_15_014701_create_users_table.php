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
        // Drop the existing users table if it exists (from Laravel's default migration)
        Schema::dropIfExists('users');
        
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('street_address')->nullable();
            $table->string('street_address_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->text('notes_by_client')->nullable();
            $table->text('notes_by_admin')->nullable();
            $table->string('profile_photo')->nullable();
            $table->enum('role', ['admin', 'technician', 'customer'])->default('customer');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('appointment_reminders')->default(true);
            $table->boolean('mailing_list')->default(true);
            $table->boolean('monthly_billing')->default(true);
            $table->enum('service_reports', ['full', 'invoice_only', 'none'])->default('full');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
