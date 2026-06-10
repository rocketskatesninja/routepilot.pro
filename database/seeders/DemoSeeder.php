<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\ManualCharge;
use App\Models\Pool;
use App\Models\ServiceLocation;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionMaterializer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo data for kicking the tyres: one tenant (Sunshine Pools) with the
 * four login roles, a handful of customers/pools on weekly service, a
 * materialized schedule, and recent completed visits with readings — enough
 * for every role dashboard to render real numbers.
 *
 * Logins (all password "password"):
 *   super@routepilot.test     super_admin (platform)
 *   admin@sunshine.test       tenant_admin
 *   marcus@sunshine.test      agent
 *   customer@sunshine.test    customer (portal)
 *
 * Run: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Platform super-admin (no tenant).
        User::factory()->superAdmin()->create([
            'first_name' => 'Platform', 'last_name' => 'Admin', 'email' => 'super@routepilot.test',
        ]);

        $tenant = Tenant::factory()->create([
            'name' => 'Sunshine Pools', 'slug' => 'sunshine',
            'brand_color' => '#0ea5e9',
            'settings' => ['hq_lat' => 28.5383, 'hq_lng' => -81.3792], // Orlando, FL
        ]);

        // Bind tenant context so tenant-owned models auto-fill tenant_id.
        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);

        $admin = User::factory()->for($tenant)->create([
            'first_name' => 'Sarah', 'last_name' => 'Owner', 'email' => 'admin@sunshine.test',
        ]);

        $marcus = User::factory()->agent()->for($tenant)->create([
            'first_name' => 'Marcus', 'last_name' => 'Bennett', 'email' => 'marcus@sunshine.test', 'map_color' => '#0ea5e9',
        ]);
        $ashley = User::factory()->agent()->for($tenant)->create([
            'first_name' => 'Ashley', 'last_name' => 'Nguyen', 'email' => 'ashley@sunshine.test', 'map_color' => '#f97316',
        ]);
        $agents = [$marcus, $ashley];

        $weekly = ServiceType::factory()->for($tenant)->create([
            'name' => 'Weekly Pool Service', 'category' => 'routine', 'price' => 50, 'estimated_duration_minutes' => 30,
            'tasks' => ['Skim surface', 'Brush walls', 'Vacuum', 'Empty baskets', 'Check pump'],
            'field_modules' => ['tasks' => true, 'chemistry' => true, 'treatments' => true, 'photos' => true],
        ]);
        ServiceType::factory()->for($tenant)->create([
            'name' => 'Chemistry Check', 'category' => 'chemistry', 'price' => 35, 'estimated_duration_minutes' => 15,
            'tasks' => ['Test FC/pH/TA', 'Add chemicals', 'Record reading'],
            'field_modules' => ['tasks' => false, 'chemistry' => true, 'treatments' => true, 'photos' => false],
        ]);

        // Chemical stock — Cal Hypo intentionally below its reorder threshold.
        foreach ([
            ['Liquid Chlorine', 'gal', 60, 15, 2.20, 4.50],
            ['Muriatic Acid', 'gal', 24, 6, 1.80, 4.00],
            ['Cal Hypo', 'lbs', 8, 10, 3.10, 6.50],
            ['Cyanuric Acid', 'lbs', 40, 10, 2.40, 5.00],
            ['Soda Ash', 'lbs', 35, 10, 1.10, 3.00],
        ] as [$chemName, $chemUnit, $stock, $reorder, $cost, $sell]) {
            ChemicalInventory::create([
                'chemical_name' => $chemName, 'unit' => $chemUnit, 'current_stock' => $stock,
                'reorder_threshold' => $reorder, 'cost_per_unit' => $cost, 'sell_price' => $sell, 'is_active' => true,
            ]);
        }

        $firstCustomer = null;
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $names = [
            ['Robert', 'Anderson'], ['Jennifer', 'Lee'], ['Michael', 'Cruz'], ['Patricia', 'Diaz'],
            ['David', 'Park'], ['Linda', 'Vo'], ['James', 'Khan'], ['Maria', 'Flores'],
        ];

        foreach ($names as $i => [$first, $last]) {
            $customer = Customer::factory()->for($tenant)->create([
                'first_name' => $first, 'last_name' => $last,
                'email' => strtolower($first).'@example.test', 'phone' => '407-555-01'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'onboarded_at' => now()->subMonths(3),
            ]);

            // First customer also gets a portal login.
            if ($i === 0) {
                $portalUser = User::factory()->customer()->for($tenant)->create([
                    'first_name' => $first, 'last_name' => $last, 'email' => 'customer@sunshine.test',
                ]);
                $customer->forceFill(['user_id' => $portalUser->id])->save();
                $firstCustomer = $customer;
            }

            $pool = Pool::factory()->for($tenant)->for($customer)->create([
                'name' => $last.' Pool', 'volume_gallons' => 12000 + $i * 1500,
                'sanitizer_type' => $i % 3 === 0 ? 'salt' : 'chlorine', 'has_heater' => $i % 2 === 0,
            ]);
            ServiceLocation::factory()->for($pool)->create([
                'city' => 'Orlando', 'state' => 'FL',
                'lat' => 28.50 + $i * 0.012, 'lng' => -81.42 + $i * 0.010,
                'gate_code' => (string) (1000 + $i * 7),
            ]);

            ServiceSubscription::factory()->for($tenant)->for($pool)->for($weekly)->create([
                'assigned_agent_id' => $agents[$i % 2]->id,
                'frequency' => 'weekly', 'preferred_day' => $days[$i % 5], 'status' => 'active',
            ]);

            // A couple of recent completed visits with readings for history/health.
            foreach ([7, 14] as $daysAgo) {
                $visit = ServiceVisit::factory()->for($tenant)->for($pool)->create([
                    'agent_id' => $agents[$i % 2]->id, 'status' => 'completed',
                    'visited_at' => now()->subDays($daysAgo), 'completed_at' => now()->subDays($daysAgo),
                ]);
                $visit->chemicalReading()->create([
                    'tenant_id' => $tenant->id,
                    'free_chlorine' => 1.5 + ($i % 3) * 0.6, 'ph' => 7.3 + ($i % 4) * 0.15,
                    'alkalinity' => 90 + $i * 3, 'calcium_hardness' => 250, 'water_temperature' => 82,
                ]);
            }
        }

        // A manual charge so the first customer carries a richer balance.
        if ($firstCustomer !== null) {
            ManualCharge::create([
                'customer_id' => $firstCustomer->id, 'description' => 'Filter cartridge replacement',
                'amount' => 89.00, 'taxable' => true, 'occurred_on' => Carbon::now()->subWeek(), 'created_by' => $admin->id,
            ]);
        }

        // Materialize the upcoming schedule from the subscriptions.
        app(SubscriptionMaterializer::class)->run($tenant->id, Carbon::now()->addWeeks(2)->toDateString());

        $this->command->info('Demo tenant "Sunshine Pools" seeded. Logins (password "password"): '
            .'super@routepilot.test, admin@sunshine.test, marcus@sunshine.test, customer@sunshine.test');
    }
}
