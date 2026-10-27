<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visit reports reach more than the customer, and can be answered:
 * - a pool's second address that gets a copy of every report (set in the field app),
 * - the company's own address that customer replies go to,
 * - which visit a customer's message from a report email is about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pools', function (Blueprint $table): void {
            $table->string('report_cc_email')->nullable()->after('notes');
        });
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('reply_to_email')->nullable()->after('postal_code');
        });
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->foreignId('service_visit_id')->nullable()->after('service_subscription_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_visit_id');
        });
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('reply_to_email');
        });
        Schema::table('pools', function (Blueprint $table): void {
            $table->dropColumn('report_cc_email');
        });
    }
};
