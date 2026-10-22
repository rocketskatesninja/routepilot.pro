<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When someone last opened Reports, so the sidebar can count the reports filed
 * since. Everyone already here starts from now, not from every report ever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('reports_seen_at')->nullable();
        });
        DB::table('users')->update(['reports_seen_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('reports_seen_at');
        });
    }
};
