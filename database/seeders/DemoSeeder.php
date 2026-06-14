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
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\SubscriptionMaterializer;
use App\Support\LandingConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo data for kicking the tyres: one tenant (Demo Company) with the
 * four login roles, a handful of customers/pools on weekly service, a
 * materialized schedule, and recent completed visits with readings — enough
 * for every role dashboard to render real numbers.
 *
 * Logins (all password "password"):
 *   admin@routepilot.pro      super_admin (platform)
 *   tenant@routepilot.pro     tenant_admin
 *   agent@routepilot.pro      agent
 *   customer@routepilot.pro   customer (portal)
 *
 * Run: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Platform super-admin (no tenant).
        User::factory()->superAdmin()->create([
            'first_name' => 'Platform', 'last_name' => 'Admin', 'email' => 'admin@routepilot.pro',
        ]);

        $tenant = Tenant::factory()->create([
            'name' => 'Demo Company', 'slug' => 'demo',
            'brand_color' => '#0ea5e9',
            'address_line1' => '1937 6th St', 'city' => 'Brunswick', 'state' => 'GA', 'postal_code' => '31520',
            'settings' => ['hq_lat' => 31.1805, 'hq_lng' => -81.4931], // Brunswick, GA
        ]);
        $tenant->forceFill(['lat' => 31.1805228, 'lng' => -81.4930654])->save();

        // Bind tenant context so tenant-owned models auto-fill tenant_id.
        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);

        $admin = User::factory()->for($tenant)->create([
            'first_name' => 'Sarah', 'last_name' => 'Owner', 'email' => 'tenant@routepilot.pro',
        ]);

        $marcus = User::factory()->agent()->for($tenant)->create([
            'first_name' => 'Marcus', 'last_name' => 'Bennett', 'email' => 'agent@routepilot.pro', 'map_color' => '#0ea5e9',
        ]);
        $ashley = User::factory()->agent()->for($tenant)->create([
            'first_name' => 'Ashley', 'last_name' => 'Nguyen', 'email' => 'ashley@sunshine.test', 'map_color' => '#f97316',
        ]);
        $agents = [$marcus, $ashley];

        // A fully fleshed-out public landing page so /t/{slug} shows off the
        // product — hero, services, team, testimonials, and FAQ — not empty sections.
        TenantSetting::setFor(
            $tenant->id,
            'landing',
            (string) json_encode(LandingConfig::sanitize(self::landingConfig([$marcus->id, $ashley->id]))),
        );

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
        $names = [
            ['Robert', 'Anderson'], ['Jennifer', 'Lee'], ['Michael', 'Cruz'], ['Patricia', 'Diaz'],
            ['David', 'Park'], ['Linda', 'Vo'], ['James', 'Khan'], ['Maria', 'Flores'],
        ];

        foreach ($names as $i => [$first, $last]) {
            $customer = Customer::factory()->for($tenant)->create([
                'first_name' => $first, 'last_name' => $last,
                'email' => strtolower($first).'@example.test', 'phone' => '912-555-01'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'onboarded_at' => now()->subMonths(3),
            ]);

            // First customer also gets a portal login.
            if ($i === 0) {
                $portalUser = User::factory()->customer()->for($tenant)->create([
                    'first_name' => $first, 'last_name' => $last, 'email' => 'customer@routepilot.pro',
                ]);
                $customer->forceFill(['user_id' => $portalUser->id])->save();
                $firstCustomer = $customer;
            }

            $pool = Pool::factory()->for($tenant)->for($customer)->create([
                'name' => $last.' Pool', 'volume_gallons' => 12000 + $i * 1500,
                'sanitizer_type' => $i % 3 === 0 ? 'salt' : 'chlorine', 'has_heater' => $i % 2 === 0,
            ]);
            // Cluster service locations around the Brunswick HQ so route maps read
            // as one tight territory (not scattered across the state).
            ServiceLocation::factory()->for($pool)->create([
                'city' => 'Brunswick', 'state' => 'GA',
                'lat' => round(31.205 - ($i % 4) * 0.016 + intdiv($i, 4) * 0.004, 6),
                'lng' => round(-81.515 + ($i % 4) * 0.014 + intdiv($i, 4) * 0.010, 6),
                'gate_code' => (string) (1000 + $i * 7),
            ]);

            // Concentrate the week so a couple of days are genuinely busy across
            // both agents — Monday gets 6 stops (3 each), Tuesday the rest.
            ServiceSubscription::factory()->for($tenant)->for($pool)->for($weekly)->create([
                'assigned_agent_id' => $agents[$i % 2]->id,
                'frequency' => 'weekly', 'preferred_day' => $i < 6 ? 'monday' : 'tuesday', 'status' => 'active',
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

        $this->command->info('Demo tenant "Demo Company" seeded. Logins (password "password"): '
            .'admin@routepilot.pro, tenant@routepilot.pro, agent@routepilot.pro, customer@routepilot.pro');
    }

    /**
     * The demo company's public landing page — a complete, brand-neutral config
     * (the company name + address inject at render). Passed agent user ids are
     * featured on the team section.
     *
     * @param  list<int>  $teamIds
     * @return array<string, mixed>
     */
    public static function landingConfig(array $teamIds = []): array
    {
        $titles = ['Lead Technician', 'Service Technician', 'Service Technician'];
        $team = [];
        foreach ($teamIds as $i => $id) {
            $team[] = ['user_id' => $id, 'title' => $titles[$i] ?? 'Service Technician', 'bio' => null];
        }

        return [
            'version' => 1,
            'seo' => [
                'title' => 'Professional Pool Service & Weekly Maintenance',
                'description' => 'Weekly pool maintenance, expert water chemistry, and fast equipment repairs. Crystal-clear water all season — get a free quote today.',
                'og_image' => null,
            ],
            'theme' => ['accent' => 'brand', 'hero_style' => 'image-right', 'show_logo' => true],
            'sections' => [
                ['key' => 'hero', 'enabled' => true,
                    'headline' => 'Your pool, perfectly maintained — all year long',
                    'subhead' => 'Weekly service, expert chemistry, and fast repairs from a team you can count on. Skip the test strips and just enjoy the swim.',
                    'cta_label' => 'Get a free quote', 'cta_anchor' => 'contact',
                    'bg_type' => 'preset', 'preset' => 'resort', 'image_path' => null,
                    'gradient_start' => '#0f172a', 'gradient_end' => '#0369a1',
                    'headline_size' => 'lg', 'headline_max_width' => 56,
                    'effects' => ['dark_overlay' => true, 'overlay_opacity' => 45, 'cta_glow' => true, 'scroll_cue' => true, 'ken_burns' => true, 'dot_matrix' => false, 'vignette' => true],
                ],
                ['key' => 'stats', 'enabled' => true, 'heading' => 'By the numbers', 'metrics' => ['pools_serviced', 'visits_completed', 'years_active']],
                ['key' => 'services', 'enabled' => true, 'heading' => 'What we do', 'items' => [
                    ['title' => 'Weekly Maintenance', 'body' => 'Skimming, brushing, vacuuming, basket cleaning, and a full chemical balance — on a dependable schedule, every week.', 'icon' => 'droplet'],
                    ['title' => 'Water Chemistry', 'body' => 'Precise testing and dosing for chlorine, pH, alkalinity, and more — water that is safe, clear, and easy on your equipment.', 'icon' => 'waves'],
                    ['title' => 'Repairs & Equipment', 'body' => 'Pumps, filters, heaters, salt systems, and automation — diagnosed and fixed right the first time.', 'icon' => 'wrench'],
                    ['title' => 'Green-to-Clean', 'body' => 'Neglected or algae-green pool? We bring it back to sparkling and get it back on a healthy schedule.', 'icon' => 'sparkles'],
                ]],
                ['key' => 'gallery', 'enabled' => true, 'heading' => 'Recent work', 'limit' => 12],
                ['key' => 'team', 'enabled' => $team !== [], 'heading' => 'Meet the team', 'members' => $team],
                ['key' => 'service_area', 'enabled' => true, 'heading' => 'Where we serve', 'radius_label' => 'Proudly serving your neighborhood and the surrounding area'],
                ['key' => 'testimonials', 'enabled' => true, 'heading' => 'Loved by homeowners', 'items' => [
                    ['quote' => "I haven't thought about my pool in months — it's just always clean. The weekly reports with photos are a great touch.", 'author' => 'Jennifer M.', 'location' => 'Weekly service customer'],
                    ['quote' => 'They turned my green, neglected pool around in under a week and it has been crystal clear ever since. Worth every penny.', 'author' => 'David R.', 'location' => 'Green-to-clean customer'],
                    ['quote' => 'Reliable, friendly, and they actually explain what they are doing. The portal makes paying and tracking everything effortless.', 'author' => 'Maria S.', 'location' => 'Homeowner'],
                ]],
                ['key' => 'faq', 'enabled' => true, 'heading' => 'Common questions', 'items' => [
                    ['q' => 'How often do you service my pool?', 'a' => 'Most homeowners are on weekly service, which keeps the water balanced and your equipment healthy year-round. We also offer bi-weekly and one-time visits — we will recommend the right cadence for your pool.'],
                    ['q' => "What's included in a weekly visit?", 'a' => 'Every visit we skim the surface, brush the walls and steps, vacuum as needed, empty the skimmer and pump baskets, test and balance your water chemistry, and check that your equipment is running properly.'],
                    ['q' => 'Do I need to be home for service?', 'a' => 'No. As long as we can reach the pool — an unlocked gate or a code — you never have to be home. We leave a service report after every visit so you know exactly what was done.'],
                    ['q' => 'My pool has turned green. Can you fix it?', 'a' => 'Absolutely. Our green-to-clean service clears the algae and rebalances the water, then we get your pool back on a regular schedule so it stays clear.'],
                    ['q' => 'How do I pay and see my service history?', 'a' => 'You get a customer portal where you can view every service report, your water chemistry over time, upcoming visits, and your invoices — and pay online in a couple of taps.'],
                    ['q' => 'Are you licensed and insured?', 'a' => 'Yes. We are fully licensed and insured, and our technicians are trained in water chemistry and pool equipment.'],
                ]],
                ['key' => 'cta', 'enabled' => true, 'headline' => 'Ready for a worry-free pool?', 'button_label' => 'Get a free quote', 'button_anchor' => 'contact'],
                ['key' => 'contact', 'enabled' => true, 'heading' => 'Get in touch', 'blurb' => 'Tell us about your pool and we will get back to you within one business day.', 'show_phone' => true],
            ],
        ];
    }
}
