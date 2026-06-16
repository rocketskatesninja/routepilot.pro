<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof-of-presence: the agent's GPS coordinates captured (best-effort) when a
 * visit is completed from the field app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_visits', function (Blueprint $table) {
            $table->decimal('completed_lat', 10, 7)->nullable();
            $table->decimal('completed_lng', 10, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_visits', function (Blueprint $table) {
            $table->dropColumn(['completed_lat', 'completed_lng']);
        });
    }
};
