<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A visit's office note: what the tech wants the company to know (repairs,
 * access trouble). Unlike `notes`, the homeowner never sees it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_visits', function (Blueprint $table): void {
            $table->text('office_notes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('service_visits', function (Blueprint $table): void {
            $table->dropColumn('office_notes');
        });
    }
};
