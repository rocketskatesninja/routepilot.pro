<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory holds repair parts as well as chemicals: a part is counted in units
 * and can carry its part number. Parts a tech uses on a visit are billed as their
 * own "parts" line on that visit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chemical_inventory', function (Blueprint $table): void {
            $table->string('kind', 12)->default('chemical')->after('chemical_name'); // chemical | part
            $table->string('part_number', 60)->nullable()->after('kind');
        });
        Schema::table('charges', function (Blueprint $table): void {
            $table->enum('kind', ['visit', 'manual', 'credit', 'chemicals', 'parts'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table): void {
            $table->enum('kind', ['visit', 'manual', 'credit', 'chemicals'])->change();
        });
        Schema::table('chemical_inventory', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'part_number']);
        });
    }
};
