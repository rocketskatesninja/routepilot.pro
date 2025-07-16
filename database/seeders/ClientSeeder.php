<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Client;
use Faker\Factory as Faker;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Create 500 clients
        for ($i = 0; $i < 500; $i++) {
            $firstName = $faker->firstName();
            $lastName = $faker->lastName();
            
            Client::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $faker->unique()->safeEmail(),
                'phone' => $faker->phoneNumber(),
                'street_address' => $faker->streetAddress(),
                'street_address_2' => $faker->optional(0.3)->secondaryAddress(),
                'city' => $faker->city(),
                'state' => $faker->stateAbbr(),
                'zip_code' => $faker->postcode(),
                'role' => $faker->randomElement(['client', 'tech', 'admin']),
                'status' => $faker->randomElement(['active', 'inactive']),
                'is_active' => $faker->boolean(80), // 80% chance of being active
                'appointment_reminders' => $faker->boolean(85),
                'mailing_list' => $faker->boolean(70),
                'monthly_billing' => $faker->boolean(75),
                'service_reports' => $faker->randomElement(['full', 'invoice_only', 'none']),
                'notes_by_client' => $faker->optional(0.4)->sentence(),
                'notes_by_admin' => $faker->optional(0.3)->sentence(),
            ]);
        }

        // Also create the original 8 clients for consistency
        $originalClients = [
            [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'email' => 'john.smith@email.com',
                'phone' => '(555) 123-4567',
                'street_address' => '123 Main Street',
                'city' => 'Phoenix',
                'state' => 'AZ',
                'zip_code' => '85001',
                'role' => 'client',
                'status' => 'active',
                'is_active' => true,
                'appointment_reminders' => true,
                'mailing_list' => true,
                'monthly_billing' => true,
                'service_reports' => 'full',
                'notes_by_admin' => 'Premium client with weekly service.',
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'email' => 'sarah.johnson@email.com',
                'phone' => '(555) 234-5678',
                'street_address' => '456 Oak Avenue',
                'street_address_2' => 'Unit 2B',
                'city' => 'Scottsdale',
                'state' => 'AZ',
                'zip_code' => '85250',
                'role' => 'client',
                'status' => 'active',
                'is_active' => true,
                'appointment_reminders' => true,
                'mailing_list' => false,
                'monthly_billing' => true,
                'service_reports' => 'invoice_only',
                'notes_by_client' => 'Prefers afternoon appointments.',
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Davis',
                'email' => 'michael.davis@email.com',
                'phone' => '(555) 345-6789',
                'street_address' => '789 Pine Road',
                'city' => 'Tempe',
                'state' => 'AZ',
                'zip_code' => '85281',
                'role' => 'client',
                'status' => 'pending',
                'is_active' => false,
                'appointment_reminders' => false,
                'mailing_list' => true,
                'monthly_billing' => false,
                'service_reports' => 'none',
                'notes_by_admin' => 'New client, needs initial assessment.',
            ],
            [
                'first_name' => 'Emily',
                'last_name' => 'Wilson',
                'email' => 'emily.wilson@email.com',
                'phone' => '(555) 456-7890',
                'street_address' => '321 Elm Street',
                'city' => 'Mesa',
                'state' => 'AZ',
                'zip_code' => '85201',
                'role' => 'client',
                'status' => 'active',
                'is_active' => true,
                'appointment_reminders' => true,
                'mailing_list' => true,
                'monthly_billing' => true,
                'service_reports' => 'full',
                'notes_by_client' => 'Has a saltwater pool system.',
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Brown',
                'email' => 'david.brown@email.com',
                'phone' => '(555) 567-8901',
                'street_address' => '654 Maple Drive',
                'city' => 'Gilbert',
                'state' => 'AZ',
                'zip_code' => '85233',
                'role' => 'client',
                'status' => 'inactive',
                'is_active' => false,
                'appointment_reminders' => false,
                'mailing_list' => false,
                'monthly_billing' => false,
                'service_reports' => 'none',
                'notes_by_admin' => 'Client moved out of state.',
            ],
            [
                'first_name' => 'Lisa',
                'last_name' => 'Garcia',
                'email' => 'lisa.garcia@email.com',
                'phone' => '(555) 678-9012',
                'street_address' => '987 Cedar Lane',
                'city' => 'Chandler',
                'state' => 'AZ',
                'zip_code' => '85224',
                'role' => 'client',
                'status' => 'active',
                'is_active' => true,
                'appointment_reminders' => true,
                'mailing_list' => true,
                'monthly_billing' => true,
                'service_reports' => 'full',
                'notes_by_client' => 'Pool has a heater, please check settings.',
            ],
            [
                'first_name' => 'Robert',
                'last_name' => 'Martinez',
                'email' => 'robert.martinez@email.com',
                'phone' => '(555) 789-0123',
                'street_address' => '147 Birch Court',
                'city' => 'Peoria',
                'state' => 'AZ',
                'zip_code' => '85345',
                'role' => 'client',
                'status' => 'active',
                'is_active' => true,
                'appointment_reminders' => true,
                'mailing_list' => true,
                'monthly_billing' => true,
                'service_reports' => 'invoice_only',
                'notes_by_admin' => 'Commercial property with multiple pools.',
            ],
            [
                'first_name' => 'Jennifer',
                'last_name' => 'Taylor',
                'email' => 'jennifer.taylor@email.com',
                'phone' => '(555) 890-1234',
                'street_address' => '258 Spruce Way',
                'city' => 'Surprise',
                'state' => 'AZ',
                'zip_code' => '85374',
                'role' => 'client',
                'status' => 'active',
                'is_active' => true,
                'appointment_reminders' => true,
                'mailing_list' => false,
                'monthly_billing' => true,
                'service_reports' => 'full',
                'notes_by_client' => 'Please use side gate for access.',
            ],
        ];

        foreach ($originalClients as $clientData) {
            Client::create($clientData);
        }
    }
} 