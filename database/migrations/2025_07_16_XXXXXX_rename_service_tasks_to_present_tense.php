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
            $table->renameColumn('vacuumed', 'vacuum');
            $table->renameColumn('brushed', 'brush');
            $table->renameColumn('skimmed', 'skim');
            $table->renameColumn('cleaned_skimmer_basket', 'clean_skimmer_basket');
            $table->renameColumn('cleaned_pump_basket', 'clean_pump_basket');
            $table->renameColumn('cleaned_pool_deck', 'clean_pool_deck');
            // Maintenance tasks
            $table->renameColumn('cleaned_filter_cartridge', 'clean_filter_cartridge');
            $table->renameColumn('backwashed_sand_filter', 'backwash_sand_filter');
            $table->renameColumn('adjusted_water_level', 'adjust_water_level');
            $table->renameColumn('adjusted_auto_fill', 'adjust_auto_fill');
            $table->renameColumn('adjusted_pump_timer', 'adjust_pump_timer');
            $table->renameColumn('adjusted_heater', 'adjust_heater');
            $table->renameColumn('checked_cover', 'check_cover');
            $table->renameColumn('checked_lights', 'check_lights');
            $table->renameColumn('checked_fountain', 'check_fountain');
            $table->renameColumn('checked_heater', 'check_heater');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Cleaning tasks
            $table->renameColumn('vacuum', 'vacuumed');
            $table->renameColumn('brush', 'brushed');
            $table->renameColumn('skim', 'skimmed');
            $table->renameColumn('clean_skimmer_basket', 'cleaned_skimmer_basket');
            $table->renameColumn('clean_pump_basket', 'cleaned_pump_basket');
            $table->renameColumn('clean_pool_deck', 'cleaned_pool_deck');
            // Maintenance tasks
            $table->renameColumn('clean_filter_cartridge', 'cleaned_filter_cartridge');
            $table->renameColumn('backwash_sand_filter', 'backwashed_sand_filter');
            $table->renameColumn('adjust_water_level', 'adjusted_water_level');
            $table->renameColumn('adjust_auto_fill', 'adjusted_auto_fill');
            $table->renameColumn('adjust_pump_timer', 'adjusted_pump_timer');
            $table->renameColumn('adjust_heater', 'adjusted_heater');
            $table->renameColumn('check_cover', 'checked_cover');
            $table->renameColumn('check_lights', 'checked_lights');
            $table->renameColumn('check_fountain', 'checked_fountain');
            $table->renameColumn('check_heater', 'checked_heater');
        });
    }
}; 