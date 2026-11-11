<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two columns Cashier 16 writes whenever it adds a price to a subscription
 * (its own migrations, which this app's billing tables were made before). Without
 * them, adding the per-pool price to a company's subscription fails. RoutePilot
 * doesn't use metered prices, so they stay empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->string('meter_id')->nullable()->after('stripe_price');
            $table->string('meter_event_name')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_items', fn (Blueprint $table) => $table->dropColumn(['meter_id', 'meter_event_name']));
    }
};
