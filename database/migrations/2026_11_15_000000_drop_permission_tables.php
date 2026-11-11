<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The Spatie permission package is gone: no permission or role was ever defined
 * (users.role decides what someone can do; extra powers are flags like
 * agent_plus). Its migration went with it, so a fresh database never has these
 * tables; on an existing one they're dropped here, all empty. Not reversible:
 * the package isn't installed to use them.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        //
    }
};
