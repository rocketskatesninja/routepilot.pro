<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TechnicianSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $technicians = [
            [
                'first_name' => 'Mike',
                'last_name' => 'Johnson',
                'email' => 'mike.johnson@routepilot.pro',
                'phone' => '(555) 123-4567',
                'street_address' => '1234 Oak Street',
                'city' => 'Phoenix',
                'state' => 'AZ',
                'zip_code' => '85001',
                'notes_by_admin' => 'Experienced pool technician with 5+ years in residential pool maintenance. Specializes in chemical balancing and equipment repair.',
                'role' => 'technician',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Martinez',
                'email' => 'sarah.martinez@routepilot.pro',
                'phone' => '(555) 234-5678',
                'street_address' => '5678 Pine Avenue',
                'city' => 'Tucson',
                'state' => 'AZ',
                'zip_code' => '85701',
                'notes_by_admin' => 'Certified pool operator with expertise in commercial pool systems. Great customer service skills.',
                'role' => 'technician',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Chen',
                'email' => 'david.chen@routepilot.pro',
                'phone' => '(555) 345-6789',
                'street_address' => '9012 Maple Drive',
                'city' => 'Mesa',
                'state' => 'AZ',
                'zip_code' => '85201',
                'notes_by_admin' => 'New technician, learning quickly. Shows great attention to detail in pool cleaning.',
                'role' => 'technician',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'first_name' => 'Lisa',
                'last_name' => 'Thompson',
                'email' => 'lisa.thompson@routepilot.pro',
                'phone' => '(555) 456-7890',
                'street_address' => '3456 Cedar Lane',
                'city' => 'Scottsdale',
                'state' => 'AZ',
                'zip_code' => '85250',
                'notes_by_admin' => 'Senior technician with 8+ years experience. Handles complex pool repairs and installations.',
                'role' => 'technician',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'first_name' => 'Robert',
                'last_name' => 'Garcia',
                'email' => 'robert.garcia@routepilot.pro',
                'phone' => '(555) 567-8901',
                'street_address' => '7890 Elm Street',
                'city' => 'Chandler',
                'state' => 'AZ',
                'zip_code' => '85224',
                'notes_by_admin' => 'Specializes in saltwater pool systems and automation. Very reliable and punctual.',
                'role' => 'technician',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'first_name' => 'Jennifer',
                'last_name' => 'Wilson',
                'email' => 'jennifer.wilson@routepilot.pro',
                'phone' => '(555) 678-9012',
                'street_address' => '2345 Birch Road',
                'city' => 'Gilbert',
                'state' => 'AZ',
                'zip_code' => '85233',
                'notes_by_admin' => 'Part-time technician, available weekends. Good with customer communication.',
                'role' => 'technician',
                'password' => Hash::make('password123'),
                'is_active' => false,
            ],
            [
                'first_name' => 'James',
                'last_name' => 'Brown',
                'email' => 'james.brown@routepilot.pro',
                'phone' => '(555) 789-0123',
                'street_address' => '4567 Willow Way',
                'city' => 'Peoria',
                'state' => 'AZ',
                'zip_code' => '85345',
                'notes_by_admin' => 'Experienced with commercial pools and spas. Certified in pool safety.',
                'role' => 'technician',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'first_name' => 'Amanda',
                'last_name' => 'Davis',
                'email' => 'amanda.davis@routepilot.pro',
                'phone' => '(555) 890-1234',
                'street_address' => '6789 Spruce Circle',
                'city' => 'Surprise',
                'state' => 'AZ',
                'zip_code' => '85374',
                'notes_by_admin' => 'New hire, completing training. Shows promise in pool maintenance.',
                'role' => 'technician',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
        ];

        foreach ($technicians as $technician) {
            User::create($technician);
        }
    }
}
