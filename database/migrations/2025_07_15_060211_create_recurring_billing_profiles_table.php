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
        Schema::create('recurring_billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->foreignId('technician_id')->constrained('users')->onDelete('cascade');
            
            // Billing details
            $table->decimal('rate_per_visit', 10, 2);
            $table->decimal('chemicals_cost', 10, 2)->default(0);
            $table->boolean('chemicals_included')->default(false);
            $table->decimal('extras_cost', 10, 2)->default(0);
            
            // Recurrence settings
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly', 'quarterly', 'custom']);
            $table->integer('frequency_value')->default(1); // For custom frequency
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = no end date
            $table->integer('day_of_week')->nullable(); // 1-7 for weekly/biweekly
            $table->integer('day_of_month')->nullable(); // 1-31 for monthly/quarterly
            
            // Status and control
            $table->enum('status', ['active', 'paused', 'cancelled'])->default('active');
            $table->boolean('auto_generate_invoices')->default(true);
            $table->integer('advance_notice_days')->default(7); // Days before service to generate invoice
            
            // Next billing info
            $table->date('next_billing_date')->nullable();
            $table->integer('invoices_generated')->default(0);
            $table->integer('total_amount_generated')->default(0);
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['client_id', 'status']);
            $table->index(['next_billing_date', 'status']);
            $table->index(['status', 'auto_generate_invoices']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_billing_profiles');
    }
};
