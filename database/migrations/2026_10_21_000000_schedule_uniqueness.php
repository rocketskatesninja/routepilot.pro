<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database-level backstops for what the scheduling code already promises: one
 * route per tech per day, and one visit per route stop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->unique(['tenant_id', 'agent_id', 'scheduled_date']);
        });
        Schema::table('service_visits', function (Blueprint $table) {
            $table->unique('route_stop_id');
        });
    }

    public function down(): void
    {
        Schema::table('service_visits', function (Blueprint $table) {
            $table->dropUnique(['route_stop_id']);
        });
        Schema::table('routes', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'agent_id', 'scheduled_date']);
        });
    }
};
