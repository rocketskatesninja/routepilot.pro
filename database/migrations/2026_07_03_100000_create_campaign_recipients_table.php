<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-recipient delivery status for a mail campaign — the source of truth behind
 * the campaign's sent/failed tallies (replaces the previously-faked counts). One
 * row per address; the queue worker flips it sent/failed as each email lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status')->default('queued'); // queued | sent | failed
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['mail_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
