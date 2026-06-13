<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Showcase flag for the public landing's recent-work gallery. Photos are
 * PRIVATE by default; a tenant_admin opts a photo in to appear on the website.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_photos', function (Blueprint $table): void {
            $table->boolean('is_showcase')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('visit_photos', function (Blueprint $table): void {
            $table->dropColumn('is_showcase');
        });
    }
};
