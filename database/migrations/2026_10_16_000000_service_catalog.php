<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The service catalog, simplified:
 *  - `requestable`: whether customers can see and request the service in the
 *    portal and on the website (existing services stay visible, as before);
 *  - `category` and `chemicals_included` go — neither was used anywhere but
 *    the Services list, and "chemicals included" was easily confused with the
 *    Water test visit section;
 *  - "seasonal" is no longer a way to sell a service (plans use an end date).
 * Also drops two never-used tables (system_settings, schedule_overrides).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->boolean('requestable')->default(true)->after('is_active');
        });
        DB::table('service_types')->where('frequency', 'seasonal')->update(['frequency' => 'weekly']);
        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn(['category', 'chemicals_included']);
        });

        Schema::dropIfExists('schedule_overrides');
        Schema::dropIfExists('system_settings');
    }

    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->boolean('chemicals_included')->default(true);
            $table->dropColumn('requestable');
        });
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
        Schema::create('schedule_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->date('original_date');
            $table->enum('action', ['skip', 'move', 'add']);
            $table->date('new_date')->nullable();
            $table->foreignId('new_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('applied')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'original_date']);
        });
    }
};
