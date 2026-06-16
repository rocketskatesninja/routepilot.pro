<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency keys for the offline field app: when an agent's queued visit
 * completion replays (flaky network / retry), the unique key lets us return the
 * original result instead of completing the visit twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_sync_keys', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_visit_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_sync_keys');
    }
};
