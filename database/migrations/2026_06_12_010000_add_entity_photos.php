<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single representative photo for pools / customers / inventory items. Tenants
 * (logo_path) and users/agents (avatar_path) already have their own columns;
 * reports use the existing visit_photos table (multiple).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pools', 'customers', 'chemical_inventory'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->string('photo_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['pools', 'customers', 'chemical_inventory'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->dropColumn('photo_path');
            });
        }
    }
};
