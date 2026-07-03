<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complimentary ("free") access, set by a super-admin from the platform billing
 * screen. When true the tenant is never billing-locked regardless of trial or
 * subscription state; the note records why (beta partner, comp, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('billing_free')->default(false)->after('trial_ends_at');
            $table->string('billing_note')->nullable()->after('billing_free');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['billing_free', 'billing_note']);
        });
    }
};
