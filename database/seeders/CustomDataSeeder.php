<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\User;
use App\Models\Location;
use App\Models\Report;
use App\Models\Invoice;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class CustomDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Create 2 technicians
        $technicians = [];
        for ($i = 1; $i <= 2; $i++) {
            $technicians[] = User::create([
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'email' => "technician{$i}@routepilot.pro",
                'phone' => $faker->phoneNumber,
                'street_address' => $faker->streetAddress,
                'city' => $faker->city,
                'state' => $faker->stateAbbr,
                'zip_code' => $faker->postcode,
                'role' => 'technician',
                'is_active' => true,
                'password' => Hash::make('password123'),
            ]);
        }

        // Create 2 clients with 5 locations each
        $clients = [];
        for ($i = 1; $i <= 2; $i++) {
            $clients[] = Client::create([
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'email' => "client{$i}@example.com",
                'phone' => $faker->phoneNumber,
                'street_address' => $faker->streetAddress,
                'city' => $faker->city,
                'state' => $faker->stateAbbr,
                'zip_code' => $faker->postcode,
                'role' => 'client',
                'status' => 'active',
                'is_active' => true,
                'appointment_reminders' => true,
                'mailing_list' => true,
                'monthly_billing' => true,
                'service_reports' => 'full',
            ]);
        }

        // Create 5 locations for each client and assign to technicians
        $locations = [];
        foreach ($clients as $index => $client) {
            $assignedTechnician = $technicians[$index]; // Assign to corresponding technician
            
            for ($j = 1; $j <= 5; $j++) {
                $location = Location::create([
                    'client_id' => $client->id,
                    'nickname' => "Location {$j}",
                    'street_address' => $faker->streetAddress,
                    'street_address_2' => $faker->optional()->secondaryAddress,
                    'city' => $faker->city,
                    'state' => $faker->stateAbbr,
                    'zip_code' => $faker->postcode,
                    'pool_type' => $faker->randomElement(['fiberglass', 'vinyl_liner', 'concrete', 'gunite']),
                    'water_type' => $faker->randomElement(['chlorine', 'salt']),
                    'access' => $faker->randomElement(['residential', 'commercial']),
                    'setting' => $faker->randomElement(['indoor', 'outdoor']),
                    'installation' => $faker->randomElement(['inground', 'above']),
                    'service_frequency' => $faker->randomElement(['semi_weekly', 'weekly', 'bi_weekly', 'monthly']),
                    'assigned_technician_id' => $assignedTechnician->id,
                    'status' => 'active',
                ]);
                
                $locations[] = $location;

                // Create 2 reports for this location
                for ($k = 1; $k <= 2; $k++) {
                    $serviceDate = $faker->dateTimeBetween('-6 months', 'now');
                    Report::create([
                        'client_id' => $client->id,
                        'location_id' => $location->id,
                        'technician_id' => $assignedTechnician->id,
                        'service_date' => $serviceDate,
                        'service_time' => $faker->time(),
                        'pool_gallons' => $faker->numberBetween(5000, 50000),
                        'fac' => $faker->numberBetween(1, 5),
                        'cc' => $faker->numberBetween(0, 2),
                        'ph' => $faker->randomFloat(1, 7.0, 8.5),
                        'alkalinity' => $faker->numberBetween(80, 120),
                        'calcium' => $faker->numberBetween(200, 400),
                        'salt' => $faker->numberBetween(2000, 4000),
                        'cya' => $faker->numberBetween(30, 80),
                        'tds' => $faker->numberBetween(500, 1500),
                        'vacuumed' => $faker->boolean,
                        'brushed' => $faker->boolean,
                        'skimmed' => $faker->boolean,
                        'cleaned_skimmer_basket' => $faker->boolean,
                        'cleaned_pump_basket' => $faker->boolean,
                        'cleaned_pool_deck' => $faker->boolean,
                        'cleaned_filter_cartridge' => $faker->boolean,
                        'backwashed_sand_filter' => $faker->boolean,
                        'adjusted_water_level' => $faker->boolean,
                        'adjusted_auto_fill' => $faker->boolean,
                        'adjusted_pump_timer' => $faker->boolean,
                        'adjusted_heater' => $faker->boolean,
                        'checked_cover' => $faker->boolean,
                        'checked_lights' => $faker->boolean,
                        'checked_fountain' => $faker->boolean,
                        'chemicals_used' => $faker->optional()->sentence,
                        'chemicals_cost' => $faker->randomFloat(2, 10, 100),
                        'other_services' => $faker->optional()->sentence,
                        'other_services_cost' => $faker->randomFloat(2, 0, 50),
                        'total_cost' => $faker->randomFloat(2, 50, 200),
                        'notes_to_client' => $faker->optional()->paragraph,
                        'notes_to_admin' => $faker->optional()->paragraph,
                        'photos' => null,
                    ]);
                }

                // Create 2 invoices for this location
                for ($k = 1; $k <= 2; $k++) {
                    $serviceDate = $faker->dateTimeBetween('-6 months', 'now');
                    $dueDate = clone $serviceDate;
                    $dueDate->modify('+30 days');
                    $ratePerVisit = $faker->randomFloat(2, 50, 150);
                    $chemicalsCost = $faker->randomFloat(2, 10, 50);
                    $extrasCost = $faker->randomFloat(2, 0, 30);
                    $totalAmount = $ratePerVisit + $chemicalsCost + $extrasCost;
                    
                    Invoice::create([
                        'client_id' => $client->id,
                        'location_id' => $location->id,
                        'technician_id' => $assignedTechnician->id,
                        'invoice_number' => 'INV-' . str_pad($location->id, 4, '0', STR_PAD_LEFT) . '-' . str_pad($k, 2, '0', STR_PAD_LEFT),
                        'service_date' => $serviceDate,
                        'due_date' => $dueDate,
                        'rate_per_visit' => $ratePerVisit,
                        'chemicals_cost' => $chemicalsCost,
                        'chemicals_included' => $faker->boolean,
                        'extras_cost' => $extrasCost,
                        'total_amount' => $totalAmount,
                        'balance' => $faker->randomFloat(2, 0, $totalAmount),
                        'status' => $faker->randomElement(['paid', 'sent', 'overdue']),
                        'notes' => $faker->optional()->sentence,
                        'notification_sent' => $faker->boolean,
                        'paid_at' => $faker->optional()->dateTimeBetween('-6 months', 'now'),
                        'recurring_profile_id' => null,
                    ]);
                }
            }
        }

        $this->command->info('Custom data seeded successfully!');
        $this->command->info("Created:");
        $this->command->info("- 2 Technicians");
        $this->command->info("- 2 Clients");
        $this->command->info("- 10 Locations (5 per client)");
        $this->command->info("- 20 Reports (2 per location)");
        $this->command->info("- 20 Invoices (2 per location)");
    }
} 