<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing suppression flag — set by the one-click unsubscribe link and
 * honoured by the campaign composer (CAN-SPAM clean). Covers customers
 * without a portal user (where per-user NotificationPreference doesn't exist).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('email_opt_out')->default(false)->after('bill_chemicals');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('email_opt_out');
        });
    }
};
