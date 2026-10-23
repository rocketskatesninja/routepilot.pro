<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a website lead was sent from, so one sender can't get round the
 * unanswered-message cap with new addresses, and a bad one can be traced.
 * Cleared by the retention sweep after a while.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('ip_address', 45)->nullable()->after('details');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('ip_address');
        });
    }
};
