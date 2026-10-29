<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who typed in a visit's report when it wasn't the tech: the office filling one
 * in from the desktop field app (shown to staff as "Entered by …").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_visits', function (Blueprint $table): void {
            $table->foreignId('entered_by_id')->nullable()->after('agent_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_visits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('entered_by_id');
        });
    }
};
