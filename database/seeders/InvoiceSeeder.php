<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;

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

        $invoiceData = [
            [
                'client_id' => $clients->first()->id,
                'location_id' => $locations->first()->id,
                'technician_id' => $technicians->first()->id,
                'service_date' => Carbon::now()->subDays(30),
                'due_date' => Carbon::now()->subDays(15),
                'rate_per_visit' => 75.00,
                'chemicals_cost' => 25.00,
                'chemicals_included' => true,
                'extras_cost' => 0.00,
                'total_amount' => 100.00,
                'balance' => 0.00,
                'status' => 'paid',
                'notes' => 'Regular weekly pool maintenance service. Water chemistry balanced, pool cleaned.',
                'paid_at' => Carbon::now()->subDays(20),
            ],
            [
                'client_id' => $clients->first()->id,
                'location_id' => $locations->first()->id,
                'technician_id' => $technicians->first()->id,
                'service_date' => Carbon::now()->subDays(15),
                'due_date' => Carbon::now()->subDays(5),
                'rate_per_visit' => 75.00,
                'chemicals_cost' => 30.00,
                'chemicals_included' => true,
                'extras_cost' => 15.00,
                'total_amount' => 120.00,
                'balance' => 120.00,
                'status' => 'overdue',
                'notes' => 'Weekly service with extra chemical treatment for algae prevention.',
            ],
            [
                'client_id' => $clients->count() > 1 ? $clients[1]->id : $clients->first()->id,
                'location_id' => $locations->count() > 1 ? $locations[1]->id : $locations->first()->id,
                'technician_id' => $technicians->count() > 1 ? $technicians[1]->id : $technicians->first()->id,
                'service_date' => Carbon::now()->subDays(10),
                'due_date' => Carbon::now()->addDays(5),
                'rate_per_visit' => 85.00,
                'chemicals_cost' => 0.00,
                'chemicals_included' => false,
                'extras_cost' => 0.00,
                'total_amount' => 85.00,
                'balance' => 85.00,
                'status' => 'sent',
                'notes' => 'Standard pool cleaning service. No chemicals needed this week.',
            ],
            [
                'client_id' => $clients->count() > 2 ? $clients[2]->id : $clients->first()->id,
                'location_id' => $locations->count() > 2 ? $locations[2]->id : $locations->first()->id,
                'technician_id' => $technicians->count() > 2 ? $technicians[2]->id : $technicians->first()->id,
                'service_date' => Carbon::now()->subDays(5),
                'due_date' => Carbon::now()->addDays(10),
                'rate_per_visit' => 90.00,
                'chemicals_cost' => 35.00,
                'chemicals_included' => true,
                'extras_cost' => 25.00,
                'total_amount' => 150.00,
                'balance' => 150.00,
                'status' => 'sent',
                'notes' => 'Premium service with extra chemical treatment and equipment inspection.',
            ],
            [
                'client_id' => $clients->count() > 3 ? $clients[3]->id : $clients->first()->id,
                'location_id' => $locations->count() > 3 ? $locations[3]->id : $locations->first()->id,
                'technician_id' => $technicians->count() > 3 ? $technicians[3]->id : $technicians->first()->id,
                'service_date' => Carbon::now()->subDays(2),
                'due_date' => Carbon::now()->addDays(13),
                'rate_per_visit' => 70.00,
                'chemicals_cost' => 20.00,
                'chemicals_included' => true,
                'extras_cost' => 0.00,
                'total_amount' => 90.00,
                'balance' => 90.00,
                'status' => 'sent',
                'notes' => 'Regular maintenance service. Pool in good condition.',
            ],
            [
                'client_id' => $clients->count() > 4 ? $clients[4]->id : $clients->first()->id,
                'location_id' => $locations->count() > 4 ? $locations[4]->id : $locations->first()->id,
                'technician_id' => $technicians->count() > 4 ? $technicians[4]->id : $technicians->first()->id,
                'service_date' => Carbon::now()->subDays(25),
                'due_date' => Carbon::now()->subDays(10),
                'rate_per_visit' => 80.00,
                'chemicals_cost' => 25.00,
                'chemicals_included' => true,
                'extras_cost' => 10.00,
                'total_amount' => 115.00,
                'balance' => 0.00,
                'status' => 'paid',
                'notes' => 'Weekly service with minor repairs to pool equipment.',
                'paid_at' => Carbon::now()->subDays(12),
            ],
            [
                'client_id' => $clients->count() > 5 ? $clients[5]->id : $clients->first()->id,
                'location_id' => $locations->count() > 5 ? $locations[5]->id : $locations->first()->id,
                'technician_id' => $technicians->count() > 5 ? $technicians[5]->id : $technicians->first()->id,
                'service_date' => Carbon::now()->subDays(20),
                'due_date' => Carbon::now()->subDays(5),
                'rate_per_visit' => 75.00,
                'chemicals_cost' => 0.00,
                'chemicals_included' => false,
                'extras_cost' => 0.00,
                'total_amount' => 75.00,
                'balance' => 75.00,
                'status' => 'overdue',
                'notes' => 'Basic pool cleaning service. No chemicals required.',
            ],
            [
                'client_id' => $clients->count() > 6 ? $clients[6]->id : $clients->first()->id,
                'location_id' => $locations->count() > 6 ? $locations[6]->id : $locations->first()->id,
                'technician_id' => $technicians->count() > 6 ? $technicians[6]->id : $technicians->first()->id,
                'service_date' => Carbon::now()->subDays(1),
                'due_date' => Carbon::now()->addDays(14),
                'rate_per_visit' => 85.00,
                'chemicals_cost' => 30.00,
                'chemicals_included' => true,
                'extras_cost' => 20.00,
                'total_amount' => 135.00,
                'balance' => 135.00,
                'status' => 'sent',
                'notes' => 'Comprehensive service including chemical treatment and equipment maintenance.',
            ],
        ];

        foreach ($invoiceData as $index => $invoice) {
            // Generate invoice number
            $invoiceNumber = 'INV-' . str_pad($index + 1, 6, '0', STR_PAD_LEFT);
            
            Invoice::create([
                'invoice_number' => $invoiceNumber,
                'client_id' => $invoice['client_id'],
                'location_id' => $invoice['location_id'],
                'technician_id' => $invoice['technician_id'],
                'service_date' => $invoice['service_date'],
                'due_date' => $invoice['due_date'],
                'rate_per_visit' => $invoice['rate_per_visit'],
                'chemicals_cost' => $invoice['chemicals_cost'],
                'chemicals_included' => $invoice['chemicals_included'],
                'extras_cost' => $invoice['extras_cost'],
                'total_amount' => $invoice['total_amount'],
                'balance' => $invoice['balance'],
                'status' => $invoice['status'],
                'notes' => $invoice['notes'],
                'paid_at' => $invoice['paid_at'] ?? null,
            ]);
        }

        $this->command->info('Created ' . count($invoiceData) . ' sample invoices.');
    }
}
