<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pools aren't all priced alike. Each plan keeps the price agreed for that pool;
 * a service's price is only where a new plan starts. Every existing plan starts
 * at its service's price today, so nobody's bill changes.
 *
 * And a company that quotes pool by pool can keep its prices from customers:
 * they see "Price on request" and get their price when the office approves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_subscriptions', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('service_type_id');
        });
        DB::table('service_subscriptions')->update([
            'price' => DB::raw('(select price from service_types where service_types.id = service_subscriptions.service_type_id)'),
        ]);

        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('show_prices')->default(true)->after('ai_assistant');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('show_prices'));
        Schema::table('service_subscriptions', fn (Blueprint $table) => $table->dropColumn('price'));
    }
};
