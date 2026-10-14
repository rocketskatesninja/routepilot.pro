<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plans get explicit scheduling fields instead of a free-form JSON blob:
 *  - starts_on    anchors "every 2 weeks" and is the date of a one-time job
 *  - ends_on      optional last day (covers seasonal service)
 *  - day_of_month for monthly plans
 * The never-used preferred-time / second-day columns and frequency_details go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_subscriptions', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('preferred_day');
            $table->date('ends_on')->nullable()->after('starts_on');
            $table->unsignedTinyInteger('day_of_month')->nullable()->after('ends_on');
        });

        // Existing plans are anchored to the day they were created.
        DB::table('service_subscriptions')->whereNull('starts_on')->update(['starts_on' => DB::raw('DATE(created_at)')]);

        Schema::table('service_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['frequency_details', 'secondary_preferred_day', 'preferred_time_start', 'preferred_time_end']);
        });
    }

    public function down(): void
    {
        Schema::table('service_subscriptions', function (Blueprint $table) {
            $table->json('frequency_details')->nullable();
            $table->string('secondary_preferred_day')->nullable();
            $table->string('preferred_time_start')->nullable();
            $table->string('preferred_time_end')->nullable();
            $table->dropColumn(['starts_on', 'ends_on', 'day_of_month']);
        });
    }
};
