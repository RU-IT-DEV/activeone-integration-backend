<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use App\Models\Company;
use App\Models\CompanyContractPeriod;
use App\Models\CompanyStatus;
use Illuminate\Support\Facades\DB;

class UpdateCompanyStatusJob implements ShouldQueue
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
        $today = Carbon::now()->startOfDay(); // Get the current date

        $companies = Company::select('companies.*', 'company_contract_periods.*', 'company_statuses.*','companies.id as id', 'company_statuses.id as status_id', 'company_contract_periods.id as contract_id')
        ->leftJoin('company_contract_periods', 'companies.id', '=', 'company_contract_periods.company_id')
        ->leftJoin('company_statuses', 'companies.id', '=', 'company_statuses.company_id')
        ->where('company_contract_periods.is_current', true)
        ->where('company_statuses.is_current', true)
        ->where(function ($query) use ($today) {
            // Companies that should become 'active'
            $query->where('company_contract_periods.contract_period_start', '<=', $today)
            ->where('company_contract_periods.contract_period_end', '<', $today)
            ->where('company_statuses.status', '!=', 'active')
            ->where('company_statuses.contract_status', '=', 'new');
        }) 
        ->orWhere(function ($query) use ($today) {
            // Companies that should become 'expired'
            $query->where('company_contract_periods.contract_period_end', '<', $today)
                // ->where('company_statuses.status', '!=', 'inactive');
                ->where('company_statuses.contract_status', '!=', 'expired')
                ->where('company_contract_periods.is_current', true)
                ->where('company_statuses.is_current', true);
        })
        ->get();

        foreach ($companies as $company) {
            // Get the related company status record using the status_id
            $companyStatus = CompanyStatus::find($company->status_id);
        
            if ($companyStatus) {
                if ($company->contract_period_end < $today) {
                    // Update to 'inactive' (expired)
                    // $companyStatus->update(['status' => 'inactive', 
                    //                         'contract_status' => 'expired']);
                    $companyStatus->update(['is_current' => false]);
                    //insert new status
                    $contract_period_end = Carbon::createFromFormat('Y-m-d', $company->contract_period_end);
                    $company_status = CompanyStatus::create([
                        'company_id' => $company->company_id,
                        'status' => 'inactive',
                        'contract_status' => 'expired',
                        'effectivity_date' => $contract_period_end->addDay(),
                        'is_current' => true,
                        'reason' => 'Automatic expired base on contract period',
                        'created_by' => 'System',
                        'contract_id' => $company->contract_id,
                        'is_executed' => 'completed'
                    ]);

                } elseif ($company->contract_period_start <= $today && $company->contract_period_end >= $today) {
                    // Update to 'active'
                    $companyStatus->update([
                        'status' => 'active',
                        'is_executed' => 'completed'
                    ]);
                }
            }
        }

        #update company statuses
        $for_updates = CompanyStatus::where('effectivity_date', '<=', $today)
            ->where('is_executed', 'pending')
            ->get();
        
        foreach ($for_updates as $update) {
            $company_id = $update->company_id;
            $contract_id = $update->contract_id;
            $status_id = $update->id;
            $contract_status = $update->contract_status;


            DB::beginTransaction();
            $find_status = CompanyStatus::find($status_id); 
            $updated_all_status = CompanyStatus::where('company_id', $company_id)
            ->update(['is_current' => false]);

            switch ($contract_status) {
                case 'suspend':
                    $find_status->update([
                        'is_current' => true,
                        'is_executed' => 'completed'
                    ]);
                break;
                case 'reactivate':
                    $find_status->update([
                        'is_current' => true,
                        'is_executed' => 'completed'
                    ]);
                break;

                case 'extend':
                    $find_status->update([
                        'is_current' => true,
                        'is_executed' => 'completed'
                    ]);
                break;
                case 'renew':
                    $updated_all_status = CompanyContractPeriod::where('company_id', $company_id)
                    ->update(['is_current' => false]);

                    $find_contract = CompanyContractPeriod::find($contract_id); 
                    $find_contract->update([
                        'is_current' => true,
                    ]);

                    $find_status->update([
                        'status' => 'active',
                        'is_current' => true,
                        'is_executed' => 'completed'
                    ]);
                
                break;
                case 'new':
                    $find_status->update([
                        'status' => 'active',
                        'is_current' => true,
                        'is_executed' => 'completed'
                    ]);
                    break;
                default:
                    throw new \Exception("Server Error. Invalid contract status.", 400);

            }
             #Process change of status
            try {
                DB::commit();
            } catch (\Throwable $th) {
                DB::rollBack();
                throw new \Exception("Server Error. {$th->getMessage()}", 500);
            }
            
        }
           
    }
}
