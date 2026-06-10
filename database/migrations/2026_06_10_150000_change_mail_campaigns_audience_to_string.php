<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relax mail_campaigns.audience from a fixed enum to a string so it can also
 * record a hand-picked "selected" send (individual recipients), not just the
 * tenants/agents/customers presets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_campaigns', function (Blueprint $table) {
            $table->string('audience', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('mail_campaigns', function (Blueprint $table) {
            $table->enum('audience', ['tenants', 'agents', 'customers'])->change();
        });
    }
};
