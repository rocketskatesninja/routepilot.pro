<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture a completed visit onto an invoice (manual_charges already had this).
 * Lets invoice generation mark which visits it billed so they aren't
 * re-invoiced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_visits', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('paid_at')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
