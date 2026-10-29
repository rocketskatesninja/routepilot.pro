<?php

declare(strict_types=1);

use App\Support\DemoParts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fill the demo company's Parts tab with common repair parts (App\Support\DemoParts)
 * so there's something to try. Only the demo company (slug "demo"); real companies
 * start with no parts. Parts it already has, by name, aren't added again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $demo = DB::table('tenants')->where('slug', 'demo')->value('id');
        if ($demo === null) {
            return;
        }

        app()->instance('tenant_id', (int) $demo);
        try {
            DemoParts::addTo();
        } finally {
            app()->forgetInstance('tenant_id');
        }
    }

    public function down(): void
    {
        // Left in place: they may have been used on visits since.
    }
};
