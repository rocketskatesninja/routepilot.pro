<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-day "unassigned" route (agent_id null) holds the day's stops that
 * aren't yet assigned to any agent, so the existing route-based drag-and-drop
 * can move a stop on/off it. Requires agent_id to be nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable(false)->change();
        });
    }
};
