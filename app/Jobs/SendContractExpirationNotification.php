<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Mail\ContractExpirationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\Company;
use App\Models\CompanyContractPeriod;
use App\Models\CompanyStatus;
use Carbon\Carbon;
use App\Http\Resources\CompanyResource;

class SendContractExpirationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public $company_ids;
    public function __construct($company_ids)
    {
        //
        $this->company_ids = $company_ids;
    }

   /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {   
        DB::reconnect();
        $today = Carbon::now()->startOfDay();
        $company_ids = $this->company_ids;
                   
        foreach ($company_ids as $company_id) {
            $company = Company::select('companies.*', 'company_contract_periods.*', 'company_statuses.*','companies.id as id','company_contract_periods.id as contract_id')
                ->leftJoin('company_contract_periods', 'companies.id', '=', 'company_contract_periods.company_id')
                ->leftJoin('company_statuses', 'companies.id', '=', 'company_statuses.company_id')
                ->where('companies.id', $company_id)
                ->where('company_statuses.is_current', true)
                ->get();
            $company_data = $company->map(function ($company) use ($today) {
                // Calculate remaining days based on the contract period end date
                $contractEndDate = Carbon::parse($company->contract_period_end);
                $remainingDays = $contractEndDate->diffInDays($today);
                // Add the remaining days to the company object
                $company->remaining_days = $remainingDays;
                return $company;
            });
            $col_company_data = CompanyResource::collection($company_data);

            $data = $col_company_data[0];

            $account_oficer =  json_decode($data['account_officer']);
            $companyName = $data['name'];
            $expirationDate = $data['contract_period_end'];
            $daysRemaining = $data['remaining_days'];
            $contract_id = $data['contract_id'];
            
            Mail::to($account_oficer->email)->send(new ContractExpirationMail($companyName, $expirationDate, $daysRemaining));

            $contract_data = CompanyContractPeriod::find($contract_id);
            $contract_data->update(['isNotified' => true]);
        }
        
    }
}
