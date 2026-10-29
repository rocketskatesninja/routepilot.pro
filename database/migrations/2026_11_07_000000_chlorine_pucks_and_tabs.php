<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chlorine pucks and tabs join the standard chemicals (counted, not weighed).
 * New companies get them at signup; this gives every existing company them too,
 * with nothing in stock. One a company already keeps under the same name is
 * linked rather than duplicated.
 */
return new class extends Migration
{
    private const ADDED = [
        'chlorine_pucks' => ['Chlorine Pucks', 'puck'],
        'chlorine_tabs' => ['Chlorine Tabs', 'tab'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $rows = DB::table('chemical_inventory')->where('tenant_id', $tenantId)->get(['id', 'chemical_name', 'standard_key']);
            foreach (self::ADDED as $key => [$name, $unit]) {
                if ($rows->contains('standard_key', $key)) {
                    continue;
                }
                $same = $rows->first(fn ($r) => $r->standard_key === null && mb_strtolower(trim($r->chemical_name)) === mb_strtolower($name));
                if ($same !== null) {
                    DB::table('chemical_inventory')->where('id', $same->id)->update(['standard_key' => $key]);

                    continue;
                }
                DB::table('chemical_inventory')->insert([
                    'tenant_id' => $tenantId, 'chemical_name' => $name, 'standard_key' => $key, 'unit' => $unit,
                    'current_stock' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Left in place: a company may already have stock or visit history on them.
    }
};
