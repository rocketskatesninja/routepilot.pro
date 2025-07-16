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
        Schema::table('locations', function (Blueprint $table) {
            // Cleaning tasks
            $table->boolean('vacuumed')->default(false)->after('notes');
            $table->boolean('brushed')->default(false)->after('vacuumed');
            $table->boolean('skimmed')->default(false)->after('brushed');
            $table->boolean('cleaned_skimmer_basket')->default(false)->after('skimmed');
            $table->boolean('cleaned_pump_basket')->default(false)->after('cleaned_skimmer_basket');
            $table->boolean('cleaned_pool_deck')->default(false)->after('cleaned_pump_basket');
            
            // Maintenance tasks
            $table->boolean('cleaned_filter_cartridge')->default(false)->after('cleaned_pool_deck');
            $table->boolean('backwashed_sand_filter')->default(false)->after('cleaned_filter_cartridge');
            $table->boolean('adjusted_water_level')->default(false)->after('backwashed_sand_filter');
            $table->boolean('adjusted_auto_fill')->default(false)->after('adjusted_water_level');
            $table->boolean('adjusted_pump_timer')->default(false)->after('adjusted_auto_fill');
            $table->boolean('adjusted_heater')->default(false)->after('adjusted_pump_timer');
            $table->boolean('checked_cover')->default(false)->after('adjusted_heater');
            $table->boolean('checked_lights')->default(false)->after('checked_cover');
            $table->boolean('checked_fountain')->default(false)->after('checked_lights');
            $table->boolean('checked_heater')->default(false)->after('checked_fountain');
            
            // Other services
            $table->json('other_services')->nullable()->after('checked_heater'); // Array of other services
            $table->decimal('other_services_cost', 8, 2)->default(0)->after('other_services');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Drop cleaning tasks
            $table->dropColumn([
                'vacuumed', 'brushed', 'skimmed', 'cleaned_skimmer_basket', 
                'cleaned_pump_basket', 'cleaned_pool_deck'
            ]);
            
            // Drop maintenance tasks
            $table->dropColumn([
                'cleaned_filter_cartridge', 'backwashed_sand_filter', 'adjusted_water_level',
                'adjusted_auto_fill', 'adjusted_pump_timer', 'adjusted_heater',
                'checked_cover', 'checked_lights', 'checked_fountain', 'checked_heater'
            ]);
            
            // Drop other services
            $table->dropColumn(['other_services', 'other_services_cost']);
        });
    }
};
