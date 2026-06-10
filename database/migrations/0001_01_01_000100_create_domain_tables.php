<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain tables — cleaned port of the legacy `business_tables` migration.
 *
 * Folds the old "add_missing_columns" churn migrations back into the base
 * definitions and adds the decided round-2/round-3 columns up front:
 *  - pools.custom_target_ranges (per-pool chemistry targets)
 *  - service_types pricing / billing treatment / field-flow module flags
 *  - service_subscriptions frequency + secondary preferred day
 *  - customers.bill_chemicals, chemical_inventory.sell_price
 * Every tenant-owned table carries tenant_id for the global TenantScope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('agent_company', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('security_code')->nullable();
            // Chemicals billed-included by default; per-customer toggle to itemize.
            $table->boolean('bill_chemicals')->default(false);
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('tenant_id');
        });

        Schema::create('pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['inground', 'above_ground', 'indoor', 'spa', 'infinity', 'other'])->default('inground');
            $table->integer('volume_gallons')->nullable();
            $table->string('surface_type')->nullable();
            $table->enum('sanitizer_type', ['chlorine', 'salt', 'bromine', 'biguanide', 'ozone', 'uv', 'other'])->default('chlorine');
            $table->enum('filter_type', ['cartridge', 'sand', 'de'])->nullable();
            $table->enum('pump_type', ['housed', 'external'])->nullable();
            $table->boolean('has_heater')->default(false);
            $table->boolean('has_automation')->default(false);
            $table->boolean('has_pool_cleaner')->default(false);
            $table->boolean('has_cover')->default(false);
            $table->boolean('has_water_feature')->default(false);
            $table->boolean('has_auto_fill')->default(false);
            $table->json('features')->nullable();
            // Per-pool chemistry target overrides (else tenant/global defaults).
            $table->json('custom_target_ranges')->nullable();
            $table->text('notes')->nullable();
            $table->json('requested_tasks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('tenant_id');
        });

        Schema::create('service_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('access_notes')->nullable();
            $table->string('gate_code')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
        });

        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly', 'one_time', 'seasonal'])->default('weekly');
            $table->integer('estimated_duration_minutes')->default(30);
            $table->decimal('price', 8, 2)->default(0);
            // Billing treatment for chemicals on this service type.
            $table->boolean('chemicals_included')->default(true);
            $table->text('description')->nullable();
            // Reusable task-checklist template.
            $table->json('tasks')->nullable();
            // Which at-pool field-flow modules show + required/optional.
            $table->json('field_modules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly', 'one_time', 'seasonal'])->default('weekly');
            $table->json('frequency_details')->nullable();
            $table->string('preferred_day')->nullable();
            $table->string('secondary_preferred_day')->nullable();
            $table->string('preferred_time_start')->nullable();
            $table->string('preferred_time_end')->nullable();
            // Vacation hold window (dated, auto-resumes); full pause via status.
            $table->date('hold_starts_at')->nullable();
            $table->date('hold_ends_at')->nullable();
            $table->enum('status', ['active', 'paused', 'cancelled'])->default('active');
            $table->timestamps();
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->date('scheduled_date');
            $table->enum('status', ['draft', 'scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->json('optimized_order')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'scheduled_date']);
            $table->index(['agent_id', 'scheduled_date']);
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('stop_order');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending');
            $table->timestamp('estimated_arrival')->nullable();
            $table->timestamp('actual_arrival')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('skip_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('service_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_stop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('visited_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->enum('status', ['in_progress', 'completed', 'skipped'])->default('in_progress');
            $table->string('signature_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'pool_id']);
        });

        Schema::create('chemical_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_visit_id')->constrained()->cascadeOnDelete();
            $table->decimal('free_chlorine', 5, 2)->nullable();
            $table->decimal('total_chlorine', 5, 2)->nullable();
            $table->decimal('ph', 4, 2)->nullable();
            $table->decimal('alkalinity', 6, 1)->nullable();
            $table->decimal('calcium_hardness', 6, 1)->nullable();
            $table->decimal('cyanuric_acid', 6, 1)->nullable();
            $table->decimal('salt', 7, 0)->nullable();
            $table->decimal('tds', 7, 0)->nullable();
            $table->decimal('phosphates', 7, 0)->nullable();
            $table->decimal('water_temperature', 5, 1)->nullable();
            $table->decimal('lsi_score', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('chemical_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_visit_id')->constrained()->cascadeOnDelete();
            $table->string('chemical_name');
            $table->decimal('amount', 8, 2);
            $table->string('unit', 50);
            $table->timestamps();
        });

        Schema::create('service_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_visit_id')->constrained()->cascadeOnDelete();
            $table->string('task_name');
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });

        Schema::create('visit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_visit_id')->constrained()->cascadeOnDelete();
            $table->string('photo_path');
            $table->string('caption')->nullable();
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

        Schema::create('chemical_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('chemical_name');
            $table->string('unit', 50);
            $table->decimal('current_stock', 10, 2)->default(0);
            $table->decimal('reorder_threshold', 10, 2)->nullable();
            $table->decimal('cost_per_unit', 8, 2)->nullable();
            // Sell price when itemizing chemicals to a customer.
            $table->decimal('sell_price', 8, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chemical_inventory_id')->nullable()->constrained('chemical_inventory')->nullOnDelete();
            $table->enum('type', ['usage', 'restock', 'adjustment']);
            $table->decimal('quantity', 10, 2);
            $table->foreignId('service_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pool_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context')->default('general');
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('mail_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('subject');
            $table->longText('body');
            $table->enum('audience', ['tenants', 'agents', 'customers']);
            $table->integer('recipient_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->boolean('email')->default(true);
            $table->boolean('in_app')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'category']);
        });

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->longText('value')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('integration_type');
            // Stored encrypted at rest via the model's `encrypted:array` cast.
            $table->text('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $tables = [
            'audit_log', 'integrations', 'system_settings', 'tenant_settings',
            'notification_preferences', 'mail_campaigns', 'chat_messages', 'chat_sessions',
            'inventory_transactions', 'chemical_inventory', 'schedule_overrides',
            'visit_photos', 'service_tasks', 'chemical_treatments', 'chemical_readings',
            'service_visits', 'route_stops', 'routes', 'service_subscriptions',
            'service_types', 'service_locations', 'pools', 'customers',
            'agent_company', 'companies',
        ];
        foreach ($tables as $t) {
            Schema::dropIfExists($t);
        }
    }
};
