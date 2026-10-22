<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A work order's price (what to charge for it, optional), and the charge that
 * billed it, so a finished work order shows it was billed and isn't billed twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->decimal('price', 10, 2)->nullable()->after('due_on');
        });
        Schema::table('charges', function (Blueprint $table): void {
            $table->foreignId('work_order_id')->nullable()->after('service_visit_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_order_id');
        });
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn('price');
        });
    }
};
