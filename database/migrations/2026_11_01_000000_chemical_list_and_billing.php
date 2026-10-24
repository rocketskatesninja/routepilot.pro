<?php

declare(strict_types=1);

use App\Support\StandardChemicals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chemicals are picked from the company's list instead of typed:
 *  - a chemical can be one of the standard ones (standard_key), so water-test
 *    suggestions find it even after it's renamed; existing rows are matched by
 *    name, and every company gets any standard chemical it doesn't have yet
 *  - a visit's treatment points at the chemical it used (older typed ones don't)
 *  - a visit can carry a "chemicals" charge beside its visit charge, one of each
 * Back up the database first: down() restores the structure, not the data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chemical_inventory', function (Blueprint $table): void {
            $table->string('standard_key', 40)->nullable()->after('chemical_name');
        });
        Schema::table('chemical_treatments', function (Blueprint $table): void {
            $table->foreignId('chemical_inventory_id')->nullable()->after('service_visit_id')->constrained('chemical_inventory')->nullOnDelete();
        });

        Schema::table('charges', function (Blueprint $table): void {
            $table->enum('kind', ['visit', 'manual', 'credit', 'chemicals'])->change();
            // The composite index goes in first, so the foreign key always has an index to use.
            $table->unique(['service_visit_id', 'kind']);
        });
        Schema::table('charges', function (Blueprint $table): void {
            $table->dropUnique(['service_visit_id']);
        });

        $now = now();
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $rows = DB::table('chemical_inventory')->where('tenant_id', $tenantId)->get(['id', 'chemical_name']);
            $have = [];
            foreach (StandardChemicals::LIST as $key => [$name, $unit]) {
                $match = $rows->first(fn ($r) => mb_strtolower(trim($r->chemical_name)) === mb_strtolower($name));
                if ($match !== null) {
                    DB::table('chemical_inventory')->where('id', $match->id)->update(['standard_key' => $key]);
                    $have[] = $key;
                }
            }
            foreach (StandardChemicals::LIST as $key => [$name, $unit]) {
                if (! in_array($key, $have, true)) {
                    DB::table('chemical_inventory')->insert([
                        'tenant_id' => $tenantId, 'chemical_name' => $name, 'standard_key' => $key, 'unit' => $unit,
                        'current_stock' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table): void {
            $table->unique('service_visit_id');
        });
        Schema::table('charges', function (Blueprint $table): void {
            $table->dropUnique(['service_visit_id', 'kind']);
            $table->enum('kind', ['visit', 'manual', 'credit'])->change();
        });
        Schema::table('chemical_treatments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('chemical_inventory_id');
        });
        Schema::table('chemical_inventory', function (Blueprint $table): void {
            $table->dropColumn('standard_key');
        });
    }
};
