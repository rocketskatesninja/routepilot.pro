<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:clear {--confirm : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all data from clients, locations, reports, technicians, and invoices tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('confirm')) {
            if (!$this->confirm('This will delete ALL clients, locations, reports, technicians, and invoices. Are you sure?')) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        $this->info('Clearing database...');

        // Clear in correct order to respect foreign key constraints
        DB::table('invoices')->delete();
        $this->info('✓ Invoices cleared');

        DB::table('reports')->delete();
        $this->info('✓ Reports cleared');

        DB::table('locations')->delete();
        $this->info('✓ Locations cleared');

        DB::table('clients')->delete();
        $this->info('✓ Clients cleared');

        DB::table('users')->where('role', '!=', 'admin')->delete();
        $this->info('✓ Technicians cleared (admin users preserved)');

        $this->newLine();
        $this->info('Database cleared successfully!');
        $this->newLine();

        $this->table(
            ['Table', 'Count'],
            [
                ['Clients', DB::table('clients')->count()],
                ['Users (non-admin)', DB::table('users')->where('role', '!=', 'admin')->count()],
                ['Invoices', DB::table('invoices')->count()],
                ['Reports', DB::table('reports')->count()],
                ['Locations', DB::table('locations')->count()],
            ]
        );
    }
}
