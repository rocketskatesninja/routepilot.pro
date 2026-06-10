<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users — staff (super_admin / tenant_admin / agent) and customers.
 *
 * The `role` enum is the SINGLE source of truth for the user's coarse
 * role (audit fix: no parallel role system). Spatie roles/permissions
 * are layered on top for granular `manage_*` abilities only. `google_id`
 * is the only OAuth join key (audit fix: never log in on email alone).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();

            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();

            // OAuth — verified Google identity, linked by id (never email).
            $table->string('google_id')->nullable()->unique();

            // Single source of truth for coarse role.
            $table->enum('role', ['super_admin', 'tenant_admin', 'agent', 'customer'])->default('customer');

            $table->string('phone')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('map_color', 7)->nullable();

            // Per-user UI prefs (theme toggle + dashboard widget layout).
            $table->string('theme')->nullable();
            $table->json('dashboard_layout')->nullable();
            $table->json('sidebar_state')->nullable();
            $table->unsignedTinyInteger('font_scale')->default(100);

            $table->text('admin_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
