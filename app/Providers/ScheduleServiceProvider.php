<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\Setting;

class ScheduleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $this->schedule($schedule);
        });
    }

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Check if automatic backups are enabled
        if (Setting::getValue('backup_enabled', '0')) {
            $frequency = Setting::getValue('backup_frequency', 'daily');
            
            switch ($frequency) {
                case 'daily':
                    $schedule->command('backup:run')
                        ->daily()
                        ->at('02:00') // Run at 2 AM
                        ->withoutOverlapping()
                        ->runInBackground()
                        ->onFailure(function () {
                            \Log::error('Scheduled daily backup failed');
                        });
                    break;
                    
                case 'weekly':
                    $schedule->command('backup:run')
                        ->weekly()
                        ->sundays()
                        ->at('02:00') // Run at 2 AM on Sundays
                        ->withoutOverlapping()
                        ->runInBackground()
                        ->onFailure(function () {
                            \Log::error('Scheduled weekly backup failed');
                        });
                    break;
                    
                case 'monthly':
                    $schedule->command('backup:run')
                        ->monthly()
                        ->at('02:00') // Run at 2 AM on the 1st of each month
                        ->withoutOverlapping()
                        ->runInBackground()
                        ->onFailure(function () {
                            \Log::error('Scheduled monthly backup failed');
                        });
                    break;
            }
        }
    }
}
