<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers choose how their dashboard looks: the customizable widget grid
 * (the default, null) or the simple Home page ('simple').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('dashboard_style', 16)->nullable()->after('dashboard_layout');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_style');
        });
    }
};
