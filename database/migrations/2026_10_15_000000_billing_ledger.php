<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Billing moves to a ledger of charges.
 *
 * Before: a visit had no price of its own — it was priced every time from "the
 * pool's first active plan", and paid/unpaid was a flag on visits and manual
 * charges. After: every billable thing is a row in `charges` (visit / manual /
 * credit) with its amount and tax fixed when it happened; a balance is simply
 * charges − payments; invoices group charges. Visits also record their plan.
 *
 * Data carried over:
 *  - every invoice line becomes a charge at exactly the amount already billed
 *    (the invoice's tax is spread across its taxable lines to the cent);
 *  - lines of void invoices become voided charges;
 *  - unbilled, unpaid manual charges and completed visits become charges — a
 *    visit at its own plan's price; a visit with no plan at all gets no charge;
 *  - items already marked paid outside an invoice were settled before the
 *    ledger existed and are not re-billed.
 * Back up the database first: down() restores the structure, not the data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_visits', function (Blueprint $table) {
            $table->foreignId('service_subscription_id')->nullable()->after('pool_id')->constrained()->nullOnDelete();
        });

        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pool_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_visit_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->enum('kind', ['visit', 'manual', 'credit']);
            $table->string('description');
            $table->decimal('amount', 10, 2); // credits are negative
            $table->boolean('taxable')->default(false);
            $table->decimal('tax', 10, 2)->default(0);
            $table->date('occurred_on');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id']);
        });

        $this->linkVisitsToPlans();
        $this->carryOverInvoiceLines();
        $this->carryOverUnbilled();

        Schema::table('service_visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('paid_at');
        });
        Schema::drop('invoice_line_items');
        Schema::drop('manual_charges');
    }

    /** A visit's plan: its route stop's plan, else the pool's only plan. */
    private function linkVisitsToPlans(): void
    {
        foreach (DB::table('service_visits')->get(['id', 'route_stop_id', 'pool_id']) as $visit) {
            $planId = $visit->route_stop_id !== null
                ? DB::table('route_stops')->where('id', $visit->route_stop_id)->value('service_subscription_id')
                : null;
            if ($planId === null) {
                $plans = DB::table('service_subscriptions')->where('pool_id', $visit->pool_id)->pluck('id');
                $planId = $plans->count() === 1 ? $plans->first() : null;
            }
            if ($planId !== null) {
                DB::table('service_visits')->where('id', $visit->id)->update(['service_subscription_id' => $planId]);
            }
        }
    }

    private function carryOverInvoiceLines(): void
    {
        $kinds = ['service' => 'visit', 'manual' => 'manual', 'chemical' => 'manual', 'credit' => 'credit'];
        $visitType = 'App\\Models\\ServiceVisit';
        $charged = [];

        foreach (DB::table('invoices')->orderBy('id')->get() as $invoice) {
            $lines = DB::table('invoice_line_items')->where('invoice_id', $invoice->id)->orderBy('id')->get();
            $taxes = $this->spreadTax((float) $invoice->tax, $lines);

            foreach ($lines as $i => $line) {
                $visit = $line->source_type === $visitType && ! isset($charged[$line->source_id])
                    ? DB::table('service_visits')->where('id', $line->source_id)->first(['id', 'pool_id', 'completed_at'])
                    : null;
                if ($visit !== null) {
                    $charged[$visit->id] = true;
                }

                DB::table('charges')->insert([
                    'tenant_id' => $invoice->tenant_id,
                    'customer_id' => $invoice->customer_id,
                    'pool_id' => $visit?->pool_id,
                    'service_visit_id' => $visit?->id,
                    'kind' => $kinds[$line->type] ?? 'manual',
                    'description' => $line->description,
                    'amount' => $line->amount,
                    'taxable' => (bool) $line->taxable,
                    'tax' => $taxes[$i],
                    'occurred_on' => Carbon::parse($visit->completed_at ?? $invoice->issued_at ?? $invoice->created_at)->toDateString(),
                    'invoice_id' => $invoice->id,
                    'voided_at' => $invoice->status === 'void' ? $invoice->updated_at : null,
                    'void_reason' => $invoice->status === 'void' ? 'Invoice '.$invoice->number.' was voided' : null,
                    'created_at' => $line->created_at,
                    'updated_at' => $line->updated_at,
                ]);
            }
        }
    }

    /**
     * The invoice's tax split across its taxable lines in proportion to their
     * amounts, rounding remainder on the last one, so the cents add up exactly.
     *
     * @param  Collection<int, stdClass>  $lines
     * @return list<float>
     */
    private function spreadTax(float $tax, Collection $lines): array
    {
        $base = (float) $lines->where('taxable', true)->sum('amount');
        $out = [];
        $left = $tax;
        $lastTaxable = $lines->keys()->filter(fn ($k) => (bool) $lines[$k]->taxable)->last();
        foreach ($lines as $i => $line) {
            if (! $line->taxable || $base <= 0) {
                $out[$i] = 0.0;

                continue;
            }
            $share = $i === $lastTaxable ? round($left, 2) : round($tax * ((float) $line->amount / $base), 2);
            $left -= $share;
            $out[$i] = $share;
        }

        return $out;
    }

    /** Unbilled, unpaid manual charges and completed visits. */
    private function carryOverUnbilled(): void
    {
        foreach (DB::table('manual_charges')->whereNull('invoice_id')->whereNull('paid_at')->get() as $c) {
            $rate = (float) DB::table('tenants')->where('id', $c->tenant_id)->value('tax_rate');
            DB::table('charges')->insert([
                'tenant_id' => $c->tenant_id,
                'customer_id' => $c->customer_id,
                'kind' => 'manual',
                'description' => $c->description,
                'amount' => $c->amount,
                'taxable' => (bool) $c->taxable,
                'tax' => $c->taxable ? round((float) $c->amount * $rate, 2) : 0,
                'occurred_on' => $c->occurred_on,
                'created_by' => $c->created_by,
                'created_at' => $c->created_at,
                'updated_at' => $c->updated_at,
            ]);
        }

        $visits = DB::table('service_visits')->where('status', 'completed')
            ->whereNull('paid_at')->whereNull('invoice_id')->whereNotNull('service_subscription_id')->get();
        foreach ($visits as $v) {
            $plan = DB::table('service_subscriptions as s')
                ->join('service_types as t', 't.id', '=', 's.service_type_id')
                ->where('s.id', $v->service_subscription_id)
                ->first(['t.name', 't.price']);
            $pool = DB::table('pools')->where('id', $v->pool_id)->first(['name', 'customer_id']);
            if ($plan === null || $pool === null || DB::table('charges')->where('service_visit_id', $v->id)->exists()) {
                continue;
            }
            DB::table('charges')->insert([
                'tenant_id' => $v->tenant_id,
                'customer_id' => $pool->customer_id,
                'pool_id' => $v->pool_id,
                'service_visit_id' => $v->id,
                'kind' => 'visit',
                'description' => $plan->name.' — '.$pool->name,
                'amount' => $plan->price,
                'taxable' => false,
                'tax' => 0,
                'occurred_on' => Carbon::parse($v->completed_at ?? $v->created_at)->toDateString(),
                'created_at' => $v->completed_at ?? $v->created_at,
                'updated_at' => $v->completed_at ?? $v->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::create('manual_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->boolean('taxable')->default(true);
            $table->date('occurred_on');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
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
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
        });
        Schema::table('service_visits', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->dropConstrainedForeignId('service_subscription_id');
        });
        Schema::drop('charges');
    }
};
