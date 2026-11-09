<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two more parts of RoutePilot a company can switch off: its public website
 * (the address then shows just its name and a customer login) and the AI
 * assistant inside the app. On for everyone until a company says otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('landing_page')->default(true)->after('inventory_tracking');
            $table->boolean('ai_assistant')->default(true)->after('landing_page');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['landing_page', 'ai_assistant']);
        });
    }
};
