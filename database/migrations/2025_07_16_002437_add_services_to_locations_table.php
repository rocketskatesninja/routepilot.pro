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
            $table->boolean('vacuum')->default(false)->after('notes');
            $table->boolean('brush')->default(false)->after('vacuum');
            $table->boolean('skim')->default(false)->after('brush');
            $table->boolean('clean_skimmer_basket')->default(false)->after('skim');
            $table->boolean('clean_pump_basket')->default(false)->after('clean_skimmer_basket');
            $table->boolean('clean_pool_deck')->default(false)->after('clean_pump_basket');
            
            // Maintenance tasks
            $table->boolean('clean_filter_cartridge')->default(false)->after('clean_pool_deck');
            $table->boolean('backwash_sand_filter')->default(false)->after('clean_filter_cartridge');
            $table->boolean('adjust_water_level')->default(false)->after('backwash_sand_filter');
            $table->boolean('adjust_auto_fill')->default(false)->after('adjust_water_level');
            $table->boolean('adjust_pump_timer')->default(false)->after('adjust_auto_fill');
            $table->boolean('adjust_heater')->default(false)->after('adjust_pump_timer');
            $table->boolean('check_cover')->default(false)->after('adjust_heater');
            $table->boolean('check_lights')->default(false)->after('check_cover');
            $table->boolean('check_fountain')->default(false)->after('check_lights');
            $table->boolean('check_heater')->default(false)->after('check_fountain');
            
            // Other services
            $table->json('other_services')->nullable()->after('check_heater'); // Array of other services
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
                'vacuum', 'brush', 'skim', 'clean_skimmer_basket', 
                'clean_pump_basket', 'clean_pool_deck'
            ]);
            
            // Drop maintenance tasks
            $table->dropColumn([
                'clean_filter_cartridge', 'backwash_sand_filter', 'adjust_water_level',
                'adjust_auto_fill', 'adjust_pump_timer', 'adjust_heater',
                'check_cover', 'check_lights', 'check_fountain', 'check_heater'
            ]);
            
            // Drop other services
            $table->dropColumn(['other_services', 'other_services_cost']);
        });
    }
};
