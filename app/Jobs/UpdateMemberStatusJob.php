<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use App\Models\MemberPlanLink;
use Illuminate\Support\Facades\DB;

class UpdateMemberStatusJob implements ShouldQueue
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
        $now = Carbon::now();
        // Expire member plans where the valid_until date has passed
        MemberPlanLink::where('status', 'active')
            ->where('valid_until', '<', $now)
            ->update(['status' => 'expired']);

    }
}
