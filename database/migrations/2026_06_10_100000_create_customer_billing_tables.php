<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer billing (homeowner → tenant): invoices + line items, manual
 * charges, payments, and saved payment methods — modeled as first-class
 * records from the start (plan: target L2 invoicing/AR + L3 in-app payments).
 * Stripe Connect wiring is layered on later; the schema is here now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('tax_rate', 6, 4)->default(0);          // e.g. 0.0675 = 6.75%
            $table->string('stripe_connect_account_id')->nullable();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('autopay_enabled')->default(false);
            $table->string('stripe_customer_id')->nullable();
            $table->unsignedBigInteger('default_payment_method_id')->nullable();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'void'])->default('draft');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id']);
        });

        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['service', 'chemical', 'manual', 'credit']);
            $table->string('description');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->boolean('taxable')->default(true);
            // Polymorphic source (a service visit, manual charge, …).
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
        });

        Schema::create('manual_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->boolean('taxable')->default(true);
            $table->date('occurred_on');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded'])->default('succeeded');
            $table->enum('method', ['card', 'cash', 'check', 'ach', 'other'])->default('card');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_payment_method_id');
            $table->string('brand')->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedSmallInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('manual_charges');
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('invoices');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['autopay_enabled', 'stripe_customer_id', 'default_payment_method_id']);
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'stripe_connect_account_id']);
        });
    }
};
