<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settlement timestamp for manual charges, so "record payment" can clear
 * them alongside unpaid visits (full invoicing remains the Phase-6 path).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_charges', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('manual_charges', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
