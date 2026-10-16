<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer requests become scheduled work.
 *
 * A request now names the catalog service it's for and, once the company
 * approves it, the plan that approval created. Pause requests carry an end
 * date; the company's reply (a note, or why it was declined) is shown to the
 * customer. Status is pending → approved | declined ("resolved" was the old
 * word for approved). A website lead records the service it picked and, once
 * converted, the customer it became.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('service_type_id')->nullable()->after('pool_id')->constrained()->nullOnDelete();
            $table->foreignId('service_subscription_id')->nullable()->after('service_type_id')->constrained()->nullOnDelete();
            $table->date('ends_on')->nullable()->after('preferred_date');
            $table->text('reply')->nullable()->after('status');
            $table->text('message')->nullable()->change();
        });
        DB::table('service_requests')->where('status', 'resolved')->update(['status' => 'approved']);

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('service_type_id')->nullable()->after('source')->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('service_type_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('service_type_id');
        });

        DB::table('service_requests')->where('status', 'approved')->update(['status' => 'resolved']);
        DB::table('service_requests')->where('status', 'declined')->update(['status' => 'resolved']);
        DB::table('service_requests')->whereNull('message')->update(['message' => '']);
        Schema::table('service_requests', function (Blueprint $table) {
            $table->text('message')->nullable(false)->change();
            $table->dropColumn(['reply', 'ends_on']);
            $table->dropConstrainedForeignId('service_subscription_id');
            $table->dropConstrainedForeignId('service_type_id');
        });
    }
};
