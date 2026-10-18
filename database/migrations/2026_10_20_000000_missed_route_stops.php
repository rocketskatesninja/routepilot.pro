<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stop nobody finished or skipped by the end of its day becomes "missed"
 * (MarkMissedStops, nightly), instead of sitting as "pending" forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped', 'missed'])->default('pending')->change();
        });
    }

    public function down(): void
    {
        DB::table('route_stops')->where('status', 'missed')->update(['status' => 'pending']);

        Schema::table('route_stops', function (Blueprint $table) {
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending')->change();
        });
    }
};
