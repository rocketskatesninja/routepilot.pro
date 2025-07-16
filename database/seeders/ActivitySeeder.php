<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Activity;
use App\Models\Client;
use App\Models\User;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = Client::all();
        $users = User::all();

        if ($clients->isEmpty() || $users->isEmpty()) {
            $this->command->info('Skipping activity seeding - no clients or users found.');
            return;
        }

        $activities = [
            [
                'action' => 'create',
                'description' => 'Client account created',
                'model_type' => Client::class,
                'model_id' => $clients->first()->id,
                'user_id' => $users->first()->id,
            ],
            [
                'action' => 'update',
                'description' => 'Updated client contact information',
                'model_type' => Client::class,
                'model_id' => $clients->first()->id,
                'user_id' => $users->first()->id,
            ],
            [
                'action' => 'create',
                'description' => 'Added new location for client',
                'model_type' => Client::class,
                'model_id' => $clients->first()->id,
                'user_id' => $users->first()->id,
            ],
            [
                'action' => 'update',
                'description' => 'Updated client preferences',
                'model_type' => Client::class,
                'model_id' => $clients->first()->id,
                'user_id' => $users->first()->id,
            ],
            [
                'action' => 'create',
                'description' => 'Generated new invoice',
                'model_type' => Client::class,
                'model_id' => $clients->first()->id,
                'user_id' => $users->first()->id,
            ],
        ];

        foreach ($activities as $activityData) {
            Activity::create([
                'user_id' => $activityData['user_id'],
                'action' => $activityData['action'],
                'model_type' => $activityData['model_type'],
                'model_id' => $activityData['model_id'],
                'description' => $activityData['description'],
                'properties' => [],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Seeder',
            ]);
        }

        $this->command->info('Created ' . count($activities) . ' sample activities.');
    }
} 