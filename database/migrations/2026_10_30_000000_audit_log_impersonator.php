<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The super-admin behind an audited action taken while impersonating, so the
 * trail doesn't pin it on the person they were signed in as.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->foreignId('impersonator_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('impersonator_id');
        });
    }
};
