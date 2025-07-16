<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Location;
use App\Models\Client;
use App\Models\User;
use Faker\Factory as Faker;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        
        $clients = Client::where('is_active', true)->get();
        $technicians = User::where('role', 'technician')->where('is_active', true)->get();

        if ($clients->isEmpty()) {
            $this->command->info('No clients found. Please run ClientSeeder first.');
            return;
        }

        if ($technicians->isEmpty()) {
            $this->command->info('No technicians found. Please run UserSeeder first.');
            return;
        }

        $this->command->info('Starting to seed 500 locations...');

        // Arizona cities and zip codes for realistic data
        $arizonaCities = [
            ['city' => 'Phoenix', 'state' => 'AZ', 'zip' => '85001'],
            ['city' => 'Scottsdale', 'state' => 'AZ', 'zip' => '85250'],
            ['city' => 'Tempe', 'state' => 'AZ', 'zip' => '85281'],
            ['city' => 'Mesa', 'state' => 'AZ', 'zip' => '85201'],
            ['city' => 'Gilbert', 'state' => 'AZ', 'zip' => '85233'],
            ['city' => 'Chandler', 'state' => 'AZ', 'zip' => '85224'],
            ['city' => 'Peoria', 'state' => 'AZ', 'zip' => '85345'],
            ['city' => 'Surprise', 'state' => 'AZ', 'zip' => '85374'],
            ['city' => 'Glendale', 'state' => 'AZ', 'zip' => '85301'],
            ['city' => 'Avondale', 'state' => 'AZ', 'zip' => '85323'],
            ['city' => 'Goodyear', 'state' => 'AZ', 'zip' => '85338'],
            ['city' => 'Buckeye', 'state' => 'AZ', 'zip' => '85326'],
            ['city' => 'Queen Creek', 'state' => 'AZ', 'zip' => '85142'],
            ['city' => 'Maricopa', 'state' => 'AZ', 'zip' => '85138'],
            ['city' => 'Casa Grande', 'state' => 'AZ', 'zip' => '85122'],
        ];

        $poolTypes = ['concrete', 'fiberglass', 'vinyl_liner'];
        $waterTypes = ['chlorine', 'salt'];
        $filterTypes = ['Sand Filter', 'Cartridge Filter', 'DE Filter'];
        $serviceFrequencies = ['weekly', 'bi_weekly', 'semi_weekly'];
        $serviceDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $accessTypes = ['residential', 'commercial'];
        $settings = ['outdoor', 'indoor'];
        $installations = ['inground', 'above'];

        $nicknamePrefixes = [
            'Main', 'Backyard', 'Family', 'Community', 'Hotel', 'Resort', 'Spa', 'Home',
            'Private', 'Public', 'Club', 'Villa', 'Estate', 'Oasis', 'Paradise', 'Retreat',
            'Luxury', 'Premium', 'Standard', 'Deluxe', 'Executive', 'Presidential'
        ];

        $nicknameSuffixes = [
            'Pool', 'Swimming Pool', 'Oasis', 'Retreat', 'Haven', 'Escape', 'Getaway',
            'Water Feature', 'Aquatic Center', 'Swim Center', 'Pool Area', 'Water Park'
        ];

        for ($i = 0; $i < 500; $i++) {
            $client = $clients->random();
            $technician = $technicians->random();
            $cityData = $faker->randomElement($arizonaCities);
            
            // Generate realistic address
            $streetNumber = $faker->numberBetween(100, 9999);
            $streetName = $faker->randomElement(['Street', 'Avenue', 'Road', 'Drive', 'Lane', 'Court', 'Way', 'Boulevard', 'Place', 'Circle']);
            $streetPrefix = $faker->randomElement(['Main', 'Oak', 'Pine', 'Elm', 'Maple', 'Cedar', 'Birch', 'Spruce', 'Willow', 'Cypress', 'Juniper', 'Aspen']);
            
            $streetAddress = $streetNumber . ' ' . $streetPrefix . ' ' . $streetName;
            
            // 20% chance of having a second address line
            $streetAddress2 = $faker->optional(0.2)->randomElement(['Unit ' . $faker->numberBetween(1, 999), 'Apt ' . $faker->numberBetween(1, 999), 'Suite ' . $faker->numberBetween(1, 999)]);
            
            // Generate realistic nickname
            $prefix = $faker->randomElement($nicknamePrefixes);
            $suffix = $faker->randomElement($nicknameSuffixes);
            $nickname = $prefix . ' ' . $suffix;
            
            // Generate realistic pool specifications
            $poolType = $faker->randomElement($poolTypes);
            $waterType = $faker->randomElement($waterTypes);
            $filterType = $faker->randomElement($filterTypes);
            $access = $faker->randomElement($accessTypes);
            $setting = $faker->randomElement($settings);
            $installation = $faker->randomElement($installations);
            
            // Generate realistic pool size based on type
            $gallons = match($poolType) {
                'concrete' => $faker->numberBetween(15000, 100000),
                'fiberglass' => $faker->numberBetween(10000, 30000),
                'vinyl_liner' => $faker->numberBetween(12000, 25000),
                default => $faker->numberBetween(15000, 50000)
            };
            
            // Generate service frequency and days
            $serviceFrequency = $faker->randomElement($serviceFrequencies);
            $serviceDay1 = $faker->randomElement($serviceDays);
            $serviceDay2 = null;
            
            if ($serviceFrequency === 'semi_weekly') {
                $serviceDay2 = $faker->randomElement(array_diff($serviceDays, [$serviceDay1]));
            }
            
            // Generate realistic pricing based on pool type and size
            $baseRate = match($poolType) {
                'concrete' => $faker->numberBetween(80, 200),
                'fiberglass' => $faker->numberBetween(70, 150),
                'vinyl_liner' => $faker->numberBetween(65, 120),
                default => $faker->numberBetween(70, 150)
            };
            
            // Adjust rate based on size and access type
            if ($gallons > 50000) $baseRate += 20;
            if ($access === 'commercial') $baseRate += 30;
            
            $ratePerVisit = $baseRate;
            
            // Generate status (90% active, 10% inactive)
            $status = $faker->boolean(90) ? 'active' : 'inactive';
            
            // Generate favorite status (20% chance)
            $isFavorite = $faker->boolean(20);
            
            // Generate chemicals included (80% chance)
            $chemicalsIncluded = $faker->boolean(80);
            
            Location::create([
                'client_id' => $client->id,
                'nickname' => $nickname,
                'street_address' => $streetAddress,
                'street_address_2' => $streetAddress2,
                'city' => $cityData['city'],
                'state' => $cityData['state'],
                'zip_code' => $cityData['zip'],
                'access' => $access,
                'pool_type' => $poolType,
                'water_type' => $waterType,
                'filter_type' => $filterType,
                'setting' => $setting,
                'installation' => $installation,
                'gallons' => $gallons,
                'service_frequency' => $serviceFrequency,
                'service_day_1' => $serviceDay1,
                'service_day_2' => $serviceDay2,
                'rate_per_visit' => $ratePerVisit,
                'chemicals_included' => $chemicalsIncluded,
                'is_favorite' => $isFavorite,
                'status' => $status,
                'assigned_technician_id' => $technician->id,
                'notes' => $faker->optional(0.3)->sentence(),
            ]);
            
            if (($i + 1) % 50 === 0) {
                $this->command->info('Created ' . ($i + 1) . ' locations...');
            }
        }

        $this->command->info('Successfully created 500 locations!');
    }
} 