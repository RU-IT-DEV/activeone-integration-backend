<?php

namespace App\Jobs;

use App\Models\BenefitPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessDeactivateBenefitPeriod implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $benefitPeriod;
    /**
     * Create a new job instance.
     */
    public function __construct(BenefitPeriod $benefitPeriod)
    {
        $this->benefitPeriod = $benefitPeriod;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::reconnect();
        if ($this->benefitPeriod) {
            return;
        }

        if ($this->benefitPeriod->is_current) {
            $this->benefitPeriod->is_current = false;
            $this->benefitPeriod->status = 'inactive';
            $this->benefitPeriod->save();

            $this->benefitPeriod->planLinks()->update(['status' => 'expired']);
        }
    }
}
