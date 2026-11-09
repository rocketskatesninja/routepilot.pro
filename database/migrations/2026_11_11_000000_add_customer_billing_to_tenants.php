<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the company bills its customers through RoutePilot (charges, balances,
 * invoices, autopay). On for everyone: a company turns it off in Company settings
 * or the Getting started checklist. Its own RoutePilot subscription is separate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('customer_billing')->default(true)->after('billing_note');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('customer_billing');
        });
    }
};
