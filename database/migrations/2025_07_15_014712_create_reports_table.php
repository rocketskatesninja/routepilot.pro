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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->foreignId('technician_id')->constrained('users')->onDelete('cascade');
            $table->date('service_date');
            $table->time('service_time');
            $table->integer('pool_gallons')->nullable();
            
            // Chemistry readings
            $table->decimal('fac', 4, 2)->nullable(); // Free Available Chlorine
            $table->decimal('cc', 4, 2)->nullable(); // Combined Chlorine
            $table->decimal('ph', 3, 1)->nullable();
            $table->integer('alkalinity')->nullable(); // Total Alkalinity
            $table->integer('calcium')->nullable(); // Calcium Hardness
            $table->integer('salt')->nullable();
            $table->integer('cya')->nullable(); // Cyanuric Acid
            $table->integer('tds')->nullable(); // Total Dissolved Solids
            
            // Cleaning tasks
            $table->boolean('vacuumed')->default(false);
            $table->boolean('brushed')->default(false);
            $table->boolean('skimmed')->default(false);
            $table->boolean('cleaned_skimmer_basket')->default(false);
            $table->boolean('cleaned_pump_basket')->default(false);
            $table->boolean('cleaned_pool_deck')->default(false);
            
            // Maintenance tasks
            $table->boolean('cleaned_filter_cartridge')->default(false);
            $table->boolean('backwashed_sand_filter')->default(false);
            $table->boolean('adjusted_water_level')->default(false);
            $table->boolean('adjusted_auto_fill')->default(false);
            $table->boolean('adjusted_pump_timer')->default(false);
            $table->boolean('adjusted_heater')->default(false);
            $table->boolean('checked_cover')->default(false);
            $table->boolean('checked_lights')->default(false);
            $table->boolean('checked_fountain')->default(false);
            $table->boolean('checked_heater')->default(false);
            
            // Chemicals and costs
            $table->json('chemicals_used')->nullable(); // Array of chemicals and amounts
            $table->decimal('chemicals_cost', 8, 2)->default(0);
            $table->json('other_services')->nullable(); // Array of other services
            $table->decimal('other_services_cost', 8, 2)->default(0);
            $table->decimal('total_cost', 8, 2)->default(0);
            
            // Notes and photos
            $table->text('notes_to_client')->nullable();
            $table->text('notes_to_admin')->nullable();
            $table->json('photos')->nullable(); // Array of photo paths
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
