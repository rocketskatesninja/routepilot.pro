<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customers choose which optional emails they get — visit reports, visit
 * reminders, and news & offers — instead of one all-or-nothing opt-out.
 * Anyone who had opted out keeps all three off. Receipts, bills and answers
 * to their own requests aren't optional and always send.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('email_visit_reports')->default(true)->after('bill_chemicals');
            $table->boolean('email_reminders')->default(true)->after('email_visit_reports');
            $table->boolean('email_news')->default(true)->after('email_reminders');
        });
        DB::table('customers')->where('email_opt_out', true)
            ->update(['email_visit_reports' => false, 'email_reminders' => false, 'email_news' => false]);

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('email_opt_out');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('email_opt_out')->default(false)->after('bill_chemicals');
        });
        // Back to one switch: opted out if they'd turned off news (the old unsubscribe meant marketing).
        DB::table('customers')->where('email_news', false)->update(['email_opt_out' => true]);

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['email_visit_reports', 'email_reminders', 'email_news']);
        });
    }
};
