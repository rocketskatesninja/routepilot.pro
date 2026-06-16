<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // The token of the user's single allowed session. Rotated on every
            // login (last-login-wins) so an account can't be used on two
            // devices at once — enforced by EnsureSingleSession. Not $fillable.
            $table->string('session_token', 64)->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('session_token');
        });
    }
};
