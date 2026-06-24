<?php

namespace App\Http\Controllers\Api;

use App\Dispatchers\JobDispatcher;
use App\Http\Controllers\Api\BaseController as BaseController;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\CompanyContractPeriod;
use App\Models\CompanyStatus;
use App\Models\CompanyCoordinators;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Auth;
use Validator;
use App\Http\Resources\CompanyResource;
use App\Jobs\SendContractExpirationNotification;

class CompanyController extends BaseController
{
    //
    public function index() {
        //  $today = Carbon::now()->startOfDay(); 
        // $companies = Company::select('companies.*', 'company_contract_periods.*', 'company_statuses.*','companies.id as id')
        //     ->leftJoin('company_contract_periods', 'companies.id', '=', 'company_contract_periods.company_id')
        //     ->leftJoin('company_statuses', 'companies.id', '=', 'company_statuses.company_id')
        //     ->where('company_contract_periods.is_current', true)
        //     ->where('company_statuses.is_current', true)
        //     ->with('coordinators')
        //     ->with('documents')
        //     ->get();

        $companies = Company::select('companies.*', 'company_contract_periods.*', 'company_statuses.*', 'companies.id as id')
            ->leftJoin('company_contract_periods', 'companies.id', '=', 'company_contract_periods.company_id')
            ->leftJoin('company_statuses', 'companies.id', '=', 'company_statuses.company_id')
            ->where('company_contract_periods.is_current', true)
            ->where('company_statuses.is_current', true)
            ->whereIn('companies.id', function ($query) {
                $query->select('company_id')
                    ->from('user_company_accesses') // or whatever the pivot table is
                    ->where('user_id', auth()->id());
            })
            ->with(['coordinators', 'documents'])
            ->get();

        $this->saveViewLog('view', "Company");
        return $this->sendResponse(CompanyResource::collection($companies), "Companies retrieved successfully.");

    }

    public function show($company_id)
    {
      
        $company = Company::select('companies.*', 'company_contract_periods.*', 'company_statuses.*','companies.id as id')
            ->leftJoin('company_contract_periods', 'companies.id', '=', 'company_contract_periods.company_id')
            ->leftJoin('company_statuses', 'companies.id', '=', 'company_statuses.company_id')
            ->where('company_contract_periods.is_current', true)
            ->where('company_statuses.is_current', true)
            ->where('companies.id', $company_id)
            ->with('coordinators')
            ->with('documents')
            ->get();

        $this->saveViewLog('view', "Company\\{$company[0]['id']}");
        return $this->sendResponse(CompanyResource::collection($company), "Company retrieved successfully.");
    }

    public function update(Request $request, $company_id)
    {
        $this->authorize('has-access', 'Companies-edit.details');
        $input_request = $request->all();
    
        $validator = Validator::make($input_request, [
            'client_id' => 'nullable',
            'name' => 'required',
            'code' => 'required',
            'contract_period_start' => 'required|date',
            'contract_period_end' => 'required|date|after:contract_period_start',
            // 'policy_period' => 'required|numeric|gt:1',
            'account_officer.name' => 'required',
            'account_officer.email' => 'required|email',
        ]);
        
        if ($validator->fails()) {
            $messages = $validator->errors()->toArray();
        
            // Replace indexes with "POC 1", "POC 2", etc.
            $customMessages = [];
            foreach ($messages as $key => $errors) {
                $newKey = preg_replace_callback(
                    '/company_coordinators\.(\d+)\./',
                    function ($matches) {
                        $index = (int)$matches[1] + 1; // Convert 0 → 1, 1 → 2, etc.
                        return "POC{$index}.";
                    },
                    $key
                );
        
                $customMessages[$newKey] = $errors;
            }
            return $this->sendError('Validator Error.', $customMessages);
        }

        // Start a database transaction
        DB::beginTransaction();
        try {
            #company model
            $company = Company::find($company_id); 
            $company->update([
                'client_id' => $request->client_id,
                'name' => $request->name,
                'code' => $request->code
            ]);
            // if ($this->hasAccess(Auth::user(), 'Companies-edit.contract')) {
                #CompanyContractPeriod model
                $company_contract_period = CompanyContractPeriod::where('company_id',$company_id) //find
                    ->where('is_current', true)
                    ->first(); 
                #CompanyContractPeriod model
                $next_renewal_date = $this->getNextRenewalDate($request->contract_period_start, $request->policy_period);
                $company_contract_period->update([
                    'contract_period_start' => $request->contract_period_start,
                    'contract_period_end' => $request->contract_period_end,
                    'policy_period' => $request->policy_period,
                    'policy' => json_encode([[
                        'policy_start' => $request->contract_period_start,
                        'policy_end' => $next_renewal_date,
                        'status' => true
                    ]]),
                    'account_officer' => json_encode([
                      'name' =>  $request->account_officer['name'],
                      'email' => $request->account_officer['email']
                    ]),
                ]);
            //}

            // Commit the transaction if no errors occur
            DB::commit();

            return $this->sendResponse([
                "name" => Auth::user()->email,
            ], "You've updated company successfully.");

        } catch (\Throwable $th) {
            DB::rollBack();
            
            return $this->sendError("Server Error.", $th->getMessage());
        }
    }

    public function store(Request $request) {
        $this->authorize('has-access', 'Companies-create');
        #Validatons
        $input_request = $request->all();

        $validator = Validator::make($input_request, [
            'client_id' => 'required|unique:companies,client_id',
            'name' => 'required',
            'code' => 'required|unique:companies,code',
            'contract_period_start' => 'required|date',
            'contract_period_end' => 'required|date|after:contract_period_start',
            // 'policy_period' => 'required|numeric|gt:1',
            'account_officer.name' => 'required',
            'account_officer.email' => 'required|email',
            'company_coordinators' => 'array',
            'company_coordinators.*.position' => 'required|string',
            'company_coordinators.*.name' => 'required|string',
            'company_coordinators.*.email' => 'email',
            'company_coordinators.*.contact_num' => 'numeric'
        ]);    
        
        if ($validator->fails()) {
            $messages = $validator->errors()->toArray();
        
            // Replace indexes with "POC 1", "POC 2", etc.
            $customMessages = [];
            foreach ($messages as $key => $errors) {
                $newKey = preg_replace_callback(
                    '/company_coordinators\.(\d+)\./',
                    function ($matches) {
                        $index = (int)$matches[1] + 1; // Convert 0 → 1, 1 → 2, etc.
                        return "POC{$index}.";
                    },
                    $key
                );
        
                $customMessages[$newKey] = $errors;
            }
            return $this->sendError('Validator Error.', $customMessages);
        }
       
        // Start a database transaction
        
        DB::beginTransaction();
        try {
            #company model
            $company = Company::create([
                'client_id' => strtolower(trim($request->client_id)),
                'name' => $request->name,
                'code' => strtolower(trim($request->code)),
            ]);
            #CompanyContractPeriod model
            $company_contract_period = CompanyContractPeriod::create([
                'company_id' => $company->id,
                'contract_period_start' => $request->contract_period_start,
                'contract_period_end' => $request->contract_period_end,
                'account_officer' => json_encode([
                  'name' =>  $request->account_officer['name'],
                  'email' => $request->account_officer['email']
                ]),
                'is_current' => true,
            ]);
            // Get the inserted ID
            $contract_id = $company_contract_period->id;
            #CompanyStatus model
            $company_status = CompanyStatus::create([
                'company_id' => $company->id,
                'status' => 'inactive',
                'contract_status' => 'new',
                'effectivity_date' => $request->contract_period_start,
                'is_current' => true,
                'reason' => NULL,
                'created_by' => Auth::user()->email,
                'contract_id' => $contract_id,
                'is_executed' => "pending"
            ]);
            #CompanyCoordinators mdoel
            $coordinators = $request->company_coordinators ? $request->company_coordinators : [];
            for ($i=0; $i<count($coordinators); $i++) {
                $company_coordinators = CompanyCoordinators::create([
                    'company_id' => $company->id,
                    'position' => $coordinators[$i]['position'],
                    'name' => $coordinators[$i]['name'],
                    'email' => $coordinators[$i]['email'],
                    'contact_num' => $coordinators[$i]['contact_num'],
                ]);
            }
           
            // Commit the transaction if no errors occur
            DB::commit();

            return $this->sendResponse([
                "name" => Auth::user()->email,
            ], "You've created company successfully.");
        } catch (\Throwable $th) {
            DB::rollBack();
            
            return $this->sendError("Server Error.", $th->getMessage());
        }
    }

    public function destroy (Request $request, $company_id)
    {
        $this->authorize('has-access', 'Companies-delete');
        DB::beginTransaction();
        try {
            $company = Company::findOrFail($company_id);
    
            // Delete related benefits
            $company->benefit()->delete();
            // Delete related members
            $company->members()->delete();
            // Delete the company itself
            $company->delete();
            DB::commit();
    
            return $this->sendResponse([
                "name" => Auth::user()->email,
            ], "You've deleted company successfully.");
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError("Server Error.", $th->getMessage());
        }

    }

    public function changeContractStatus (Request $request, $company) {
        $this->authorize('has-access', 'Companies-edit.contract');
        $input_request = $request->all();
        $input_request['company_id'] = $company;
        $contract_status = $input_request['contract_status'];

        $company_contract_period = CompanyContractPeriod::where('company_id',$input_request['company_id']) //find
            ->where('is_current', true)
            ->first(); 
        $company_contract_status = CompanyStatus::where('company_id',$input_request['company_id']) //find
            ->where('is_current', true)
            ->first(); 
        // Switch case based on contract_status
        switch ($contract_status) {
            case 'Suspend':
                // Suspend logic
                $validator = Validator::make($input_request, [
                    'company_id' => 'required',
                    'effectivity_date' => 'required|date|after:'.$company_contract_period->contract_period_start.'|before_or_equal:'.$company_contract_period->contract_period_end,
                    'reason' => 'required',
                ]);
                if ($validator->fails()) {
                    return $this->sendError('Validator Error.', $validator->errors());
                }
                #status holder 
                $hold_contract_status = 'suspend';
                $status = 'inactive';
                $effectivity_date = $input_request['effectivity_date'];

                if ($company_contract_status->contract_status == $hold_contract_status) {
                    return $this->sendError("Validator Error.", ["reason"=> ["Contract status is already suspended."]], 400);
                }
                break;

            case 'Extend':
                // Extend logic
                $validator = Validator::make($input_request, [
                    'company_id' => 'required',
                    'effectivity_date' => 'required|date|after:'.$company_contract_period->contract_period_end,
                    'reason' => 'required',
                ]);
                $hold_contract_status = 'extend';
                $status = 'active';
                $effectivity_date = $company_contract_period->contract_period_end;

                if ($validator->fails()) {
                    return $this->sendError('Validator Error.', $validator->errors());
                }
                
                break;

            case 'Reactivate':
                // Reactivate logic
                $validator = Validator::make($input_request, [
                    'company_id' => 'required',
                    'effectivity_date' => 'required|date|after:'.$company_contract_status->effectivity_date.'|before_or_equal:'.$company_contract_period->contract_period_end,
                    'reason' => 'required',
                ]);
                if ($company_contract_status->status === 'active') {
                    return $this->sendError("Validator Error.", ["reason"=> ["Contract status is already active."]], 400);
                }
                if ($validator->fails()) {
                    return $this->sendError('Validator Error.', $validator->errors());
                }
                #status holder 
                $hold_contract_status = 'reactivate';
                $status = 'active';
                $effectivity_date = $input_request['effectivity_date'];
                break;

            case 'Renew': 
                // company renewal logic
                // if ()  { #iF company has pending renewal validator
                // }
                $validator = Validator::make($input_request, [
                    'company_id' => 'required',
                    'contract_period_start' => 'required|date|after_or_equal:'.$company_contract_period->contract_period_end,
                    'contract_period_end' => 'required',
                    'reason' => 'required',
                ]);
                if ($validator->fails()) {
                    return $this->sendError('Validator Error.', $validator->errors());
                }

                #insert contract period
                $hold_contract_status = 'renew';
                $status = 'inactive';
                $effectivity_date = $input_request['contract_period_start'];
                break;

            default:
                // Default case (not necessary, as validation covers this)
                return $this->sendError("Server Error.", 'Invalid contract status.', 400);
        }

        #Process change of status
        DB::beginTransaction();
        try {
            # change status
            // $company_contract_status->update([
            //     'is_current' => false
            // ]);

            #insert new status
            
            if ($hold_contract_status === 'extend'){
                #CompanyContractPeriod extend expiration date
                $company_contract_period->update([
                    'contract_period_end' => $input_request['effectivity_date']
                ]);
            } 
            $contract_id = $company_contract_period->id;
            if ($hold_contract_status === 'renew'){
                #Insert New CompanyContractPeriod
                $new_contract_period = CompanyContractPeriod::create([
                    'company_id' => $input_request['company_id'],
                    'contract_period_start' => $input_request['contract_period_start'],
                    'contract_period_end' => $input_request['contract_period_end'],
                    'account_officer' => $company_contract_period->account_officer,
                    'is_current' => false,
                ]);
                // Get the inserted ID
                $contract_id = $new_contract_period->id;
            } 

            #CompanyStatus model
            $company_status = CompanyStatus::create([
                'company_id' => $input_request['company_id'],
                'status' => $status,
                'contract_status' => $hold_contract_status,
                'effectivity_date' => $effectivity_date,
                'is_current' => false,
                'reason' => $input_request['reason'],
                'created_by' => Auth::user()->email,
                'contract_id' => $contract_id
            ]);


            DB::commit();

            return $this->sendResponse([
                "name" => Auth::user()->email,
            ],  "You've successfully change contract status.");
            
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError("Server Error.", $th->getMessage());
        }

    }

    public function getNextRenewalDate($startDate,$months)
    {
        // Parse the start date
        $startDate = Carbon::parse($startDate);
        // Add 12 months to get the next renewal date
        $nextRenewalDate = $startDate->addMonths($months);
        return $nextRenewalDate->toDateString(); // Format as 'Y-m-d'
    }

    public function contractStatusHistory($company_id) {
        $this->authorize('has-access', 'Companies-view.contracts');
        $company_status = CompanyStatus::select('company_statuses.*')
        ->where('company_statuses.company_id', $company_id)
        ->orderBy('id', 'desc')
        ->get();

        return $this->sendResponse($company_status, "status history retrieved successfully.");
    }

    public function contractsStatusCount () {
        $today = Carbon::now()->startOfDay();

        // Count Active Contracts
        $activeCount = Company::whereHas('CompanyStatus',function ($query) use ($today) {
            $query->where('is_current', true);
            $query->where('status', 'active');
        })->count();
        
        // Count Inactive Contracts
        $inactiveCount = Company::whereHas('CompanyStatus', function ($query) use ($today) {
            $query->where('is_current', true);
            $query->where('status', 'inactive');
        })->count();

        // Count Near Due Contracts (e.g., within the next 90 days)
        $nearDueCount = Company::whereHas('companyContractPeriod', function ($query) use ($today) {
            $query->where('is_current', true)
                  ->where('contract_period_start', '<=', $today)
                  ->where('contract_period_end', '>', $today)
                  ->where('contract_period_end', '<=', $today->copy()->addDays(90));
        })->count();

        // Return the response as JSON
        $data = [
            'active' => $activeCount,
            'inactive' => $inactiveCount,
            'nearDue' => $nearDueCount,
        ];
        return $this->sendResponse($data, "Contracts status count retrieved successfully.");
      
    }

    public function getNearDueCompanies  () {

         // Define today's date and the near due threshold (90 days from today)
         $today = Carbon::now()->startOfDay();
         $nearDueThreshold = $today->copy()->addDays(90);
 
         // Fetch companies that are near due (expiring within the next 90 days)
        $nearDueCompanies = Company::select('companies.*', 'company_contract_periods.*', 'company_statuses.*','companies.id as id')
            ->leftJoin('company_contract_periods', 'companies.id', '=', 'company_contract_periods.company_id')
            ->leftJoin('company_statuses', 'companies.id', '=', 'company_statuses.company_id')
            ->whereHas('companyContractPeriod', function ($query) use ($today, $nearDueThreshold) {
            $query->where('company_contract_periods.is_current', true)
                ->where('company_statuses.is_current', true)
                ->where('company_contract_periods.contract_period_start', '<=', $today)
                ->where('company_contract_periods.contract_period_end', '>', $today)
                ->where('company_contract_periods.contract_period_end', '<=', $nearDueThreshold);
        })->get();

         // Add remaining days for each company
        $nearDueCompanies = $nearDueCompanies->map(function ($company) use ($today) {
            // Calculate remaining days based on the contract period end date
            $contractEndDate = Carbon::parse($company->contract_period_end);
            $remainingDays = $contractEndDate->diffInDays($today);
            // Add the remaining days to the company object
            $company->remaining_days = $remainingDays;
            return $company;
        });
        return $this->sendResponse(CompanyResource::collection($nearDueCompanies), "Neardue companies retrieved successfully.");
        
    }

    public function sendCompaniesContractExpiration (Request $request) {
        $company_ids = $request->company_ids;
        // Validate the input
        $request->validate([
            'company_ids' => 'required|array'
        ]);

        JobDispatcher::dispatch(
            new SendContractExpirationNotification($company_ids)
        );

        return $this->sendResponse([
            "name" => Auth::user()->email,
        ], "You've sent notification successfully.");
    }

    public function getDropdownCompanies() { #GET dropdwon companies baes on their company access 
        $companies = Company::select('id', 'name', 'code', 'form_version', 'email_version')
            ->whereIn('companies.id', function ($query) {
                $query->select('company_id')
                    ->from('user_company_accesses') // or whatever the pivot table is
                    ->where('user_id', auth()->id());
            })
            ->get();
        

        return $this->sendResponse($companies, "Companies retrieved successfully.");
    }

    public function getAllCompanies() {        
        $companies = Company::select('companies.*', 'company_contract_periods.*', 'company_statuses.*','companies.id as id')
            ->leftJoin('company_contract_periods', 'companies.id', '=', 'company_contract_periods.company_id')
            ->leftJoin('company_statuses', 'companies.id', '=', 'company_statuses.company_id')
            ->where('company_contract_periods.is_current', true)
            ->where('company_statuses.is_current', true)
            ->with('coordinators')
            ->with('documents')
            ->get();

        $this->saveViewLog('view', "Company");
        return $this->sendResponse(CompanyResource::collection($companies), "Companies retrieved successfully.");
    }

    public function getCompanyBenefits(Company $company)
    {
        $benefits = $company->benefit()->with(['categories', 'periods'])->get();

        return $this->sendResponse($benefits, "Company benefits retrieved successfully.");
    }

}
