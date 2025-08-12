<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearMapCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-map-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing map geocoding cache...');
        
        try {
            $cleared = \App\Services\MapService::clearCache();
            
            if ($cleared) {
                $this->info('✅ Map cache cleared successfully!');
            } else {
                $this->error('❌ Failed to clear map cache');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ Error clearing map cache: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
