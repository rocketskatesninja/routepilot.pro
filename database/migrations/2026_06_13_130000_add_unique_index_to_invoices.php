<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice numbers are per-tenant sequential. A unique (tenant_id, number) index
 * is the final guard against duplicate numbers under concurrency (the number is
 * derived from the max issued number, not count()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'number']);
        });
    }
};
