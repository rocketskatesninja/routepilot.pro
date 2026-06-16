<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Last-known position per agent (one row each, upserted on every ping).
        // Ephemeral by design — purged after a short retention window.
        Schema::create('agent_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->unique('agent_id'); // one row per agent (upserted)
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedSmallInteger('heading')->nullable();
            $table->unsignedInteger('accuracy')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['tenant_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_locations');
    }
};
