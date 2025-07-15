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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->foreignId('technician_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('service_date');
            $table->date('due_date');
            $table->decimal('rate_per_visit', 8, 2);
            $table->decimal('chemicals_cost', 8, 2)->default(0);
            $table->boolean('chemicals_included')->default(true);
            $table->decimal('extras_cost', 8, 2)->default(0);
            $table->decimal('total_amount', 8, 2);
            $table->decimal('balance', 8, 2)->default(0);
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
