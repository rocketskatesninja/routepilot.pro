<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work orders: the company's task list. A tech raises one from a visit (what
 * needs doing later: a part to order, a repair to book), or the office adds one.
 * A visit raises at most one, so replays and edits never duplicate it; deleting
 * the visit's report leaves the task in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('details')->nullable();
            $table->string('status')->default('open'); // open | done
            $table->boolean('urgent')->default(false);
            $table->date('due_on')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pool_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_visit_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
