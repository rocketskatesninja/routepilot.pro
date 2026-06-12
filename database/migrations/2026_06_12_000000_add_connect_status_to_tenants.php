<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the tenant's connected Stripe account can accept charges (payouts
 * enabled). Gates routing customer payments to the tenant vs the platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('stripe_connect_charges_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('stripe_connect_charges_enabled');
        });
    }
};
