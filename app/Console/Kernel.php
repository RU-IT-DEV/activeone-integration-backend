<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\SchedulerLog;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        // $schedule->job(new \App\Jobs\UpdateCompanyStatusJob)->daily();
        #1 
        $schedule->job(new \App\Jobs\UpdateCompanyStatusJob)
        ->everyMinute()
        ->onSuccess(function () {
            SchedulerLog::create([
                'job_name' => 'UpdateCompanyStatusJob',
                'status' => 'success',
                'run_time' => now(),
            ]);
        })
        ->onFailure(function (\Throwable $exception) {
            SchedulerLog::create([
                'job_name' => 'UpdateCompanyStatusJob',
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'run_time' => now(),
            ]);
        });
        #2
        $schedule->job(new \App\Jobs\UpdateBenefitStatusJob)
        ->everyMinute()
        ->onSuccess(function () {
            SchedulerLog::create([
                'job_name' => 'UpdateBenefitPeriodStatus',
                'status' => 'success',
                'run_time' => now(),
            ]);
        })
        ->onFailure(function (\Throwable $exception) {
            SchedulerLog::create([
                'job_name' => 'UpdateBenefitPeriodStatus',
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'run_time' => now(),
            ]);
        });
        #3
        $schedule->job(new \App\Jobs\UpdateMemberStatusJob)
        ->everyMinute()
        ->onSuccess(function () {
            SchedulerLog::create([
                'job_name' => 'UpdateMemberStatusJob',
                'status' => 'success',
                'run_time' => now(),
            ]);
        })
        ->onFailure(function (\Throwable $exception) {
            SchedulerLog::create([
                'job_name' => 'UpdateMemberStatusJob',
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'run_time' => now(),
            ]);
        });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
