<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema tidy-up. Nothing here held data anyone used:
 *  - Columns nothing reads or writes: a route's optimized order (stops carry
 *    their own order), a stop's arrival time (no ETAs), a visit's signature, an
 *    invoice's PDF path (PDFs are drawn on demand), a pool's requested tasks, a
 *    user's theme and font scale (both live in the browser now), a customer's
 *    security code (never shown or edited; gate codes are on the pool's
 *    location), and a company's settings JSON (superseded by tenant_settings and
 *    the tenants.lat/lng columns).
 *  - The companies feature from the old app: its two tables and the customer
 *    column pointing at them, never used.
 *  - service_visits.route_stop_id had two indexes; the unique one is enough.
 *  - Three references with no foreign key, so they could point at rows that
 *    are gone: they now do what the rest of the schema does. Any that already
 *    point nowhere are cleared first.
 *  - Stripe payments are looked up by their payment intent: indexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', fn (Blueprint $table) => $table->dropColumn('optimized_order'));
        Schema::table('route_stops', fn (Blueprint $table) => $table->dropColumn('actual_arrival'));
        Schema::table('service_visits', fn (Blueprint $table) => $table->dropColumn('signature_path'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('pdf_path'));
        Schema::table('pools', fn (Blueprint $table) => $table->dropColumn('requested_tasks'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['theme', 'font_scale']));
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('settings'));
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn(['company_id', 'security_code']);
        });
        Schema::dropIfExists('agent_company');
        Schema::dropIfExists('companies');

        // MySQL made this index for the foreign key before the unique one existed.
        if (Schema::hasIndex('service_visits', 'service_visits_route_stop_id_foreign')) {
            Schema::table('service_visits', fn (Blueprint $table) => $table->dropIndex('service_visits_route_stop_id_foreign'));
        }

        DB::table('chat_sessions')->whereNotNull('tenant_id')->whereNotIn('tenant_id', DB::table('tenants')->select('id'))->update(['tenant_id' => null]);
        DB::table('field_sync_keys')->whereNotNull('service_visit_id')->whereNotIn('service_visit_id', DB::table('service_visits')->select('id'))->update(['service_visit_id' => null]);
        DB::table('customers')->whereNotNull('default_payment_method_id')->whereNotIn('default_payment_method_id', DB::table('payment_methods')->select('id'))->update(['default_payment_method_id' => null]);

        // A company's public chats go with the company; a deleted visit or card just clears the pointer.
        Schema::table('chat_sessions', fn (Blueprint $table) => $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete());
        Schema::table('field_sync_keys', fn (Blueprint $table) => $table->foreign('service_visit_id')->references('id')->on('service_visits')->nullOnDelete());
        Schema::table('customers', fn (Blueprint $table) => $table->foreign('default_payment_method_id')->references('id')->on('payment_methods')->nullOnDelete());

        Schema::table('payments', fn (Blueprint $table) => $table->index('stripe_payment_intent_id'));
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex(['stripe_payment_intent_id']));
        Schema::table('customers', fn (Blueprint $table) => $table->dropForeign(['default_payment_method_id']));
        Schema::table('field_sync_keys', fn (Blueprint $table) => $table->dropForeign(['service_visit_id']));
        Schema::table('chat_sessions', fn (Blueprint $table) => $table->dropForeign(['tenant_id']));

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
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('security_code')->nullable()->after('admin_notes');
        });
        Schema::table('tenants', fn (Blueprint $table) => $table->json('settings')->nullable());
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme')->nullable();
            $table->unsignedTinyInteger('font_scale')->default(100);
        });
        Schema::table('pools', fn (Blueprint $table) => $table->json('requested_tasks')->nullable());
        Schema::table('invoices', fn (Blueprint $table) => $table->string('pdf_path')->nullable());
        Schema::table('service_visits', fn (Blueprint $table) => $table->string('signature_path')->nullable());
        Schema::table('route_stops', fn (Blueprint $table) => $table->timestamp('actual_arrival')->nullable());
        Schema::table('routes', fn (Blueprint $table) => $table->json('optimized_order')->nullable());
    }
};
