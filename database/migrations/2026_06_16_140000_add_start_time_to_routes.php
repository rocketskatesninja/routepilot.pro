<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table): void {
            // The agent's workday start ("HH:MM") that ETAs accumulate from.
            // Null → falls back to the tenant's day_start setting, then config.
            $table->string('start_time', 5)->nullable()->after('scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table): void {
            $table->dropColumn('start_time');
        });
    }
};
