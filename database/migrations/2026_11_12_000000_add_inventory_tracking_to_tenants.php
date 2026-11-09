<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the company counts its stock in RoutePilot (stock levels, low-stock
 * alerts, repair parts). On for everyone: a company turns it off in Company
 * settings or the Getting started checklist. With it off, techs still record the
 * chemicals they add, from a plain list of names and units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('inventory_tracking')->default(true)->after('customer_billing');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('inventory_tracking');
        });
    }
};
