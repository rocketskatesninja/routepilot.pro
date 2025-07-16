<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Location;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Str;

class JohnSmithLocationAndReportSeeder extends Seeder
{
    public function run()
    {
        // Find or create John Smith
        $client = Client::firstOrCreate(
            [
                'first_name' => 'John',
                'last_name' => 'Smith',
            ],
            [
                'email' => 'john.smith@example.com',
                'phone' => '555-1234',
                'is_active' => true,
                'status' => 'active',
                'role' => 'client',
            ]
        );

        // Add two locations
        $location1 = Location::firstOrCreate([
            'client_id' => $client->id,
            'nickname' => 'Smith Residence',
        ], [
            'city' => 'Atlanta',
            'state' => 'GA',
            'status' => 'active',
            'street_address' => '123 Main St',
            'zip_code' => '30301',
        ]);

        $location2 = Location::firstOrCreate([
            'client_id' => $client->id,
            'nickname' => 'Smith Lake House',
        ], [
            'city' => 'Lakeview',
            'state' => 'GA',
            'status' => 'active',
            'street_address' => '456 Lake Dr',
            'zip_code' => '30302',
        ]);

        // Get a technician
        $technician = User::where('role', 'technician')->first();
        if (!$technician) {
            $technician = User::factory()->create(['role' => 'technician']);
        }

        // Add two reports for those locations (if Report model exists)
        if (class_exists(Report::class)) {
            Report::firstOrCreate([
                'location_id' => $location1->id,
                'client_id' => $client->id,
                'technician_id' => $technician->id,
                'service_date' => now()->subDays(7)->toDateString(),
                'service_time' => '10:00:00',
            ], [
                'notes_to_client' => 'All chemicals balanced. No issues found.',
            ]);

            Report::firstOrCreate([
                'location_id' => $location2->id,
                'client_id' => $client->id,
                'technician_id' => $technician->id,
                'service_date' => now()->subDays(30)->toDateString(),
                'service_time' => '11:00:00',
            ], [
                'notes_to_client' => 'Filter cleaned. Minor debris removed.',
            ]);
        }
    }
} 