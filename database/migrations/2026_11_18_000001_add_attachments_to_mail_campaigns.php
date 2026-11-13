<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The files sent with an Email people message: [{name, size, mime, path}] where path
 * (on the private disk) becomes null once the files are cleared after 90 days; the
 * names stay so Recent still shows what was sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_campaigns', function (Blueprint $table) {
            $table->json('attachments')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('mail_campaigns', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};
