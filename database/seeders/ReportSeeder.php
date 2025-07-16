<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Report;
use App\Models\Client;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;

class ReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        
        $clients = Client::where('is_active', true)->get();
        $locations = Location::where('status', 'active')->get();
        $technicians = User::where('role', 'technician')->where('is_active', true)->get();

        if ($clients->isEmpty() || $locations->isEmpty() || $technicians->isEmpty()) {
            $this->command->info('Skipping report seeding - no clients, locations, or technicians found.');
            return;
        }

        $this->command->info('Starting to seed 500 reports...');

        for ($i = 0; $i < 500; $i++) {
            $client = $clients->random();
            $location = $locations->random();
            $technician = $technicians->random();
            
            // Generate realistic pool chemistry values
            $fac = $faker->randomFloat(1, 1.0, 5.0);
            $cc = $faker->randomFloat(1, 0.0, 1.0);
            $ph = $faker->randomFloat(1, 7.0, 8.0);
            $alkalinity = $faker->numberBetween(80, 150);
            $calcium = $faker->numberBetween(200, 400);
            $salt = $faker->optional(0.3)->numberBetween(2500, 4000); // 30% chance of salt pool
            $cya = $faker->numberBetween(30, 80);
            $tds = $faker->numberBetween(500, 2000);
            
            // Generate pool size
            $poolGallons = $faker->randomElement([15000, 18000, 22000, 25000, 30000, 35000, 40000, 50000]);
            
            // Generate service time
            $serviceTime = $faker->time('H:i:s', '18:00:00');
            
            // Generate service date (within last 2 years)
            $serviceDate = $faker->dateTimeBetween('-2 years', 'now');
            
            // Generate chemicals used
            $chemicalsUsed = [];
            $chemicalsCost = 0;
            
            $chemicalTypes = [
                'chlorine' => ['amount' => $faker->numberBetween(1, 5), 'unit' => 'lbs', 'cost' => 8],
                'shock' => ['amount' => $faker->numberBetween(1, 3), 'unit' => 'lbs', 'cost' => 12],
                'algaecide' => ['amount' => $faker->numberBetween(1, 2), 'unit' => 'qt', 'cost' => 15],
                'ph_plus' => ['amount' => $faker->numberBetween(1, 3), 'unit' => 'lbs', 'cost' => 10],
                'ph_minus' => ['amount' => $faker->numberBetween(1, 3), 'unit' => 'lbs', 'cost' => 10],
                'stabilizer' => ['amount' => $faker->numberBetween(1, 3), 'unit' => 'lbs', 'cost' => 12],
            ];
            
            if ($salt) {
                $chemicalsUsed['salt'] = $faker->numberBetween(2, 8) . ' lbs';
                $chemicalsCost += 20;
            }
            
            // Add 2-4 random chemicals
            $selectedChemicals = $faker->randomElements(array_keys($chemicalTypes), $faker->numberBetween(2, 4));
            foreach ($selectedChemicals as $chemical) {
                $amount = $chemicalTypes[$chemical]['amount'];
                $unit = $chemicalTypes[$chemical]['unit'];
                $chemicalsUsed[$chemical] = $amount . ' ' . $unit;
                $chemicalsCost += $amount * $chemicalTypes[$chemical]['cost'];
            }
            
            // Generate other services
            $otherServices = [];
            $otherServicesCost = 0;
            
            $serviceTypes = [
                'filter_cleaning' => ['description' => 'Filter cartridge cleaned', 'cost' => 15],
                'backwash' => ['description' => 'Sand filter backwashed', 'cost' => 10],
                'heater_check' => ['description' => 'Heater settings verified', 'cost' => 5],
                'timer_adjustment' => ['description' => 'Pump timer adjusted', 'cost' => 5],
                'deck_cleaning' => ['description' => 'Pool deck pressure washed', 'cost' => 25],
                'cover_repair' => ['description' => 'Pool cover repaired', 'cost' => 30],
            ];
            
            // 20% chance of additional services
            if ($faker->boolean(20)) {
                $selectedServices = $faker->randomElements(array_keys($serviceTypes), $faker->numberBetween(1, 2));
                foreach ($selectedServices as $service) {
                    $otherServices[$service] = $serviceTypes[$service]['description'];
                    $otherServicesCost += $serviceTypes[$service]['cost'];
                }
            }
            
            $totalCost = $chemicalsCost + $otherServicesCost;
            
            // Generate realistic notes
            $notesToClient = $this->generateClientNotes($faker, $fac, $ph, $alkalinity);
            $notesToAdmin = $this->generateAdminNotes($faker, $totalCost, $otherServices);
            
            Report::create([
                'client_id' => $client->id,
                'location_id' => $location->id,
                'technician_id' => $technician->id,
                'service_date' => $serviceDate,
                'service_time' => $serviceTime,
                'pool_gallons' => $poolGallons,
                'fac' => $fac,
                'cc' => $cc,
                'ph' => $ph,
                'alkalinity' => $alkalinity,
                'calcium' => $calcium,
                'salt' => $salt,
                'cya' => $cya,
                'tds' => $tds,
                'vacuumed' => $faker->boolean(90),
                'brushed' => $faker->boolean(85),
                'skimmed' => $faker->boolean(95),
                'cleaned_skimmer_basket' => $faker->boolean(80),
                'cleaned_pump_basket' => $faker->boolean(70),
                'cleaned_pool_deck' => $faker->boolean(60),
                'cleaned_filter_cartridge' => $faker->boolean(30),
                'backwashed_sand_filter' => $faker->boolean(25),
                'adjusted_water_level' => $faker->boolean(75),
                'adjusted_auto_fill' => $faker->boolean(40),
                'adjusted_pump_timer' => $faker->boolean(35),
                'adjusted_heater' => $faker->boolean(20),
                'checked_cover' => $faker->boolean(50),
                'checked_lights' => $faker->boolean(80),
                'checked_fountain' => $faker->boolean(30),
                'checked_heater' => $faker->boolean(25),
                'chemicals_used' => $chemicalsUsed,
                'chemicals_cost' => $chemicalsCost,
                'other_services' => $otherServices,
                'other_services_cost' => $otherServicesCost,
                'total_cost' => $totalCost,
                'notes_to_client' => $notesToClient,
                'notes_to_admin' => $notesToAdmin,
            ]);
            
            if (($i + 1) % 50 === 0) {
                $this->command->info('Created ' . ($i + 1) . ' reports...');
            }
        }

        $this->command->info('Successfully created 500 reports!');
    }
    
    private function generateClientNotes($faker, $fac, $ph, $alkalinity)
    {
        $notes = [];
        
        if ($fac >= 3.0) {
            $notes[] = 'Chlorine levels are excellent';
        } elseif ($fac >= 1.0) {
            $notes[] = 'Chlorine levels are within normal range';
        } else {
            $notes[] = 'Chlorine levels were low and have been adjusted';
        }
        
        if ($ph >= 7.2 && $ph <= 7.8) {
            $notes[] = 'pH is perfectly balanced';
        } elseif ($ph < 7.2) {
            $notes[] = 'pH was low and has been raised to optimal levels';
        } else {
            $notes[] = 'pH was high and has been lowered to optimal levels';
        }
        
        if ($alkalinity >= 80 && $alkalinity <= 150) {
            $notes[] = 'Alkalinity is stable';
        } else {
            $notes[] = 'Alkalinity has been adjusted for better water balance';
        }
        
        $notes[] = 'Pool water is clear and clean';
        $notes[] = 'All equipment is functioning properly';
        
        if ($faker->boolean(80)) {
            $notes[] = 'Pool is ready for use';
        }
        
        return implode('. ', $notes) . '.';
    }
    
    private function generateAdminNotes($faker, $totalCost, $otherServices)
    {
        $notes = [];
        
        if ($totalCost > 50) {
            $notes[] = 'Higher than average service cost due to additional chemicals/services';
        } else {
            $notes[] = 'Standard service completed';
        }
        
        if (!empty($otherServices)) {
            $notes[] = 'Additional services provided as requested';
        }
        
        if ($faker->boolean(90)) {
            $notes[] = 'No issues reported';
        } else {
            $notes[] = 'Minor issues noted and addressed';
        }
        
        $notes[] = 'Client satisfied with service';
        
        return implode('. ', $notes) . '.';
    }
} 