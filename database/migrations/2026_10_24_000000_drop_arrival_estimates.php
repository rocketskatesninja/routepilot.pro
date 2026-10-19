<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RoutePilot no longer estimates when a tech will arrive (nobody is promised a
 * time), so drop the per-stop estimate and the route start time it counted from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_stops', function (Blueprint $table): void {
            $table->dropColumn('estimated_arrival');
        });
        Schema::table('routes', function (Blueprint $table): void {
            $table->dropColumn('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('route_stops', function (Blueprint $table): void {
            $table->timestamp('estimated_arrival')->nullable()->after('status');
        });
        Schema::table('routes', function (Blueprint $table): void {
            $table->string('start_time', 5)->nullable()->after('scheduled_date');
        });
    }
};
