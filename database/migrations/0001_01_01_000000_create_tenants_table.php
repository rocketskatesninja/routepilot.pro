<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenants — the top-level account each company belongs to.
 *
 * Cleaned port of the legacy `tenants` table. The old per-tier `plan`
 * enum (trial/starter/professional/enterprise) is dropped in favour of
 * the new base+per-pool billing model (columns added in Phase 6). Adds
 * `timezone` (US/DST-aware scheduling) and `primary_domain` (custom
 * domain resolution) from line one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Custom-domain + subdomain resolution (see ResolveTenant).
            $table->string('primary_domain')->nullable()->unique();

            // Localisation — US-focused, DST-aware.
            $table->string('timezone')->default('America/New_York');

            // Branding (overlays both ops + daylight themes).
            $table->string('logo_path')->nullable();
            $table->string('brand_color', 7)->nullable();

            // Stripe customer handle (billing columns proper land in Phase 6).
            $table->string('stripe_id')->nullable()->index();

            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
