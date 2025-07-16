<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = Client::where('is_active', true)->get();
        $locations = Location::where('status', 'active')->get();
        $technicians = User::where('role', 'technician')->where('is_active', true)->get();

        if ($clients->isEmpty() || $locations->isEmpty() || $technicians->isEmpty()) {
            $this->command->info('Skipping invoice seeding - no clients, locations, or technicians found.');
            return;
        }

        $faker = Faker::create();
        
        // Statuses with weighted probabilities
        $statuses = [
            'paid' => 0.4,      // 40% paid
            'sent' => 0.35,     // 35% sent
            'overdue' => 0.15,  // 15% overdue
            'draft' => 0.1,     // 10% draft
        ];

        // Service types with different rate ranges
        $serviceTypes = [
            'basic' => ['min' => 60, 'max' => 80],
            'standard' => ['min' => 75, 'max' => 95],
            'premium' => ['min' => 90, 'max' => 120],
            'deluxe' => ['min' => 110, 'max' => 150],
        ];

        $this->command->info('Generating 500 invoices...');

        for ($i = 1; $i <= 500; $i++) {
            // Random selection
            $client = $clients->random();
            $location = $locations->random();
            $technician = $technicians->random();
            
            // Random service date (within last 2 years)
            $serviceDate = Carbon::now()->subDays(rand(1, 730));
            
            // Due date (7-30 days after service date)
            $dueDate = $serviceDate->copy()->addDays(rand(7, 30));
            
            // Random service type
            $serviceType = array_rand($serviceTypes);
            $ratePerVisit = $faker->randomFloat(2, $serviceTypes[$serviceType]['min'], $serviceTypes[$serviceType]['max']);
            
            // Chemicals cost (0-50% of rate)
            $chemicalsCost = $faker->optional(0.7)->randomFloat(2, 0, $ratePerVisit * 0.5);
            $chemicalsIncluded = $faker->boolean(70); // 70% chance chemicals are included
            
            // Extras cost (0-30% of rate)
            $extrasCost = $faker->optional(0.4)->randomFloat(2, 0, $ratePerVisit * 0.3);
            
            $totalAmount = $ratePerVisit + ($chemicalsCost ?? 0) + ($extrasCost ?? 0);
            
            // Determine status with weighted probability
            $status = $this->getWeightedRandomStatus($statuses);
            
            // Balance and paid_at logic
            $balance = $totalAmount;
            $paidAt = null;
            
            if ($status === 'paid') {
                $balance = 0;
                $paidAt = $faker->dateTimeBetween($serviceDate, min($dueDate, Carbon::now()));
            } elseif ($status === 'overdue') {
                // Ensure overdue invoices are past due date
                $dueDate = Carbon::now()->subDays(rand(1, 60));
            }
            
            // Generate invoice number
            $invoiceNumber = 'INV-' . str_pad($i, 6, '0', STR_PAD_LEFT);
            
            // Generate realistic notes
            $notes = $this->generateNotes($serviceType, $chemicalsCost, $extrasCost);
            
            Invoice::create([
                'invoice_number' => $invoiceNumber,
                'client_id' => $client->id,
                'location_id' => $location->id,
                'technician_id' => $technician->id,
                'service_date' => $serviceDate,
                'due_date' => $dueDate,
                'rate_per_visit' => $ratePerVisit,
                'chemicals_cost' => $chemicalsCost ?? 0,
                'chemicals_included' => $chemicalsIncluded,
                'extras_cost' => $extrasCost ?? 0,
                'total_amount' => $totalAmount,
                'balance' => $balance,
                'status' => $status,
                'notes' => $notes,
                'paid_at' => $paidAt,
                'notification_sent' => $faker->boolean(80), // 80% chance notification was sent
            ]);
            
            // Progress indicator every 50 invoices
            if ($i % 50 === 0) {
                $this->command->info("Generated {$i} invoices...");
            }
        }

        $this->command->info('Successfully created 500 invoices.');
    }
    
    /**
     * Get weighted random status
     */
    private function getWeightedRandomStatus($statuses)
    {
        $rand = mt_rand() / mt_getrandmax();
        $cumulative = 0;
        
        foreach ($statuses as $status => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $status;
            }
        }
        
        return 'sent'; // fallback
    }
    
    /**
     * Generate realistic service notes
     */
    private function generateNotes($serviceType, $chemicalsCost, $extrasCost)
    {
        $notes = [];
        
        // Base service notes
        $baseNotes = [
            'Pool cleaned and skimmed. Water chemistry balanced.',
            'Regular weekly maintenance completed. Equipment checked.',
            'Pool vacuumed and brushed. Chemical levels adjusted.',
            'Standard cleaning service performed. Water clarity excellent.',
            'Routine maintenance completed. Pool equipment functioning properly.',
        ];
        
        $notes[] = $baseNotes[array_rand($baseNotes)];
        
        // Add chemical notes if applicable
        if ($chemicalsCost > 0) {
            $chemicalNotes = [
                'Additional chlorine treatment applied.',
                'pH levels corrected with chemical treatment.',
                'Algae prevention chemicals added.',
                'Water balance chemicals administered.',
                'Shock treatment applied for water clarity.',
            ];
            $notes[] = $chemicalNotes[array_rand($chemicalNotes)];
        }
        
        // Add extra service notes if applicable
        if ($extrasCost > 0) {
            $extraNotes = [
                'Equipment inspection and minor repairs completed.',
                'Pool filter cleaned and backwashed.',
                'Additional equipment maintenance performed.',
                'Emergency repair work completed.',
                'Special cleaning treatment applied.',
            ];
            $notes[] = $extraNotes[array_rand($extraNotes)];
        }
        
        // Add service type specific notes
        if ($serviceType === 'premium' || $serviceType === 'deluxe') {
            $premiumNotes = [
                'Premium service includes detailed equipment inspection.',
                'Comprehensive water testing and treatment performed.',
                'Deluxe service with enhanced cleaning procedures.',
                'Thorough equipment maintenance and water treatment.',
            ];
            $notes[] = $premiumNotes[array_rand($premiumNotes)];
        }
        
        return implode(' ', $notes);
    }
}
