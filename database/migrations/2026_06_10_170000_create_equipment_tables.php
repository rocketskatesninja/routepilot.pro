<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-pool equipment (pump / filter / heater / salt cell …) + a service/repair
 * log. Repairs can be billed to the customer as a manual charge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pool_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // pump, filter, heater, salt_cell, cleaner, automation, other
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('serial')->nullable();
            $table->date('installed_on')->nullable();
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'pool_id']);
        });

        Schema::create('equipment_service_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pool_equipment_id')->constrained('pool_equipment')->cascadeOnDelete();
            $table->foreignId('service_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->date('serviced_on');
            $table->string('description');
            $table->decimal('cost', 10, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['tenant_id', 'pool_equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_service_log');
        Schema::dropIfExists('pool_equipment');
    }
};
