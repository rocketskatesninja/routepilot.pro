<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let chat_sessions back the public lead-capture chatbot: an anonymous visitor
 * session has no user but belongs to a tenant and is resumed by a visitor token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('tenant_id')->nullable()->after('user_id');
            $table->string('visitor_token', 64)->nullable()->after('tenant_id');
            $table->index('visitor_token');
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropIndex(['visitor_token']);
            $table->dropColumn(['tenant_id', 'visitor_token']);
        });
    }
};
