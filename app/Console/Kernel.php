<?php

namespace App\Console;

use App\Console\Commands\ImportLocations;
use App\Console\Commands\ReEncodeCustomFieldNames;
use App\Console\Commands\RestoreDeletedUsers;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('bewsys:inventory-alerts')->daily();
        $schedule->command('bewsys:expiring-alerts')->daily();
        $schedule->command('bewsys:expected-checkin')->daily();
        $schedule->command('bewsys:backup')->weekly();
        $schedule->command('backup:clean')->daily();
        $schedule->command('bewsys:upcoming-audits')->daily();
        $schedule->command('auth:clear-resets')->everyFifteenMinutes();
    }

    /**
     * This method is required by Laravel to handle any console routes
     * that are defined in routes/console.php.
     */
    protected function commands()
    {
        require base_path('routes/console.php');
        $this->load(__DIR__.'/Commands');
    }
}
