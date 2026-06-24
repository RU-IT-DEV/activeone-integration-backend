<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use App\Models\BenefitPeriod;
use Illuminate\Support\Facades\DB;

class UpdateBenefitStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::reconnect();
        //
        $now = Carbon::now();
         // Update expired benefit periods
         BenefitPeriod::where('status', 'active')
         ->where('expiration_date', '<', $now)
         ->update(['status' => 'inactive']);
    }
}
