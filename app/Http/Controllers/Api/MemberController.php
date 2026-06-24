<?php

namespace App\Http\Controllers\Api;

use App\Dispatchers\JobDispatcher;
use App\Helper\CloudTasksHelper;
use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\MemberProfileResource;
use App\Jobs\MemberBulkUploadJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Benefit;
use App\Models\BenefitPeriod;
use App\Models\Members;
use App\Models\MemberPlanLink;
use App\Models\MemberPlanBucket;
use App\Http\Controllers\Api\BenefitsController as BenefitsController;
use App\Http\Resources\MemberResource;
use App\Helper\ClaimsHelper;
use Auth;
use Validator;
use Exception;


class MemberController extends BaseController
{
    # OLDE
    // public function index() {
    //     # new 
    //     try {
    //         $user = auth()->user();
    //         if (!$user) {
    //             return $this->sendError('Unauthorized.', [], 401);
    //         }
        
    //         // Get the company codes the user has access to
    //         $accessibleCompanyCodes = DB::table('user_company_accesses')
    //             ->join('companies', 'user_company_accesses.company_id', '=', 'companies.id')
    //             ->where('user_company_accesses.user_id', $user->id)
    //             ->pluck('companies.code');
        
    //         // Fetch members with relationships
    //         $members = Members::whereIn('company_code', $accessibleCompanyCodes)
    //             ->with([
    //                 'company',
    //                 // Only eager load planLink.benefit if it exists
    //                 'planLink' => function ($query) {
    //                     $query->with(['benefit' => function ($q) {
    //                         // Only select columns that exist to prevent prod errors
    //                         $q->select('id', 'name'); // adjust columns as per your DB
    //                     }]);
    //                 },
    //                 'bankDetails'
    //             ])
    //             ->withCount(['pending_claims'])
    //             ->get();
        
    //         return $this->sendResponse(MemberResource::collection($members), 'Members fetched successfully.');
    //     } catch (\Illuminate\Database\QueryException $qe) {
    //         // DB query error
    //         \Log::error('DB Query Error fetching members', [
    //             'user_id' => $user->id ?? null,
    //             'error' => $qe->getMessage(),
    //             'trace' => $qe->getTraceAsString()
    //         ]);
    //         return $this->sendError('Database query error: ' . $qe->getMessage(), [], 500);
    //     } catch (\Exception $e) {
    //         // Other errors
    //         \Log::error('Error fetching members', [
    //             'user_id' => $user->id ?? null,
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return $this->sendError('Server error: ' . $e->getMessage(), [], 500);
    //     }
        
    // }
    # NEW 
    public function index(Request $request) {
        try {
            $user = auth()->user();
    
            if (!$user) {
                return $this->sendError('Unauthorized.', [], 401);
            }
    
            // Get the company codes the user has access to
            $accessibleCompanyCodes = DB::table('user_company_accesses')
                ->join('companies', 'user_company_accesses.company_id', '=', 'companies.id')
                ->where('user_company_accesses.user_id', $user->id)
                ->pluck('companies.code')
                ->toArray(); // Convert to array for whereIn
    
            // If no accessible companies, return empty response
            if (empty($accessibleCompanyCodes)) {
                return $this->sendResponse([], 'No accessible members.');
            }
    
            $search = $request->query('search', '');
            // Fetch members with relationships safely
            $membersQuery = Members::whereIn('company_code', $accessibleCompanyCodes);
            if (!empty($search)) {
                $membersQuery->where(function ($query) use ($search) {
                    $query->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('flexicare_id', 'LIKE', "%{$search}%")
                        ->orWhere('gender', 'LIKE', "%{$search}%")
                        ->orWhere('employee_no', 'LIKE', "%{$search}%")
                        ->orWhere('division', 'LIKE', "%{$search}%")
                        ->orWhere('company_code', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }
            $members = $membersQuery->with([
                'company',
                // Safe nested eager loading with select
                'planLink' => function ($query) {
                    $query->with([
                        'benefitPeriod.benefit' => function ($q) {
                            $q->select('id', 'name'); // only select existing columns
                        }
                    ]);
                },
                'bankDetails'
            ])->withCount('pending_claims')
            ->paginate(
                $request->itemsPerPage,
                ['*'],
                'page',
                $request->page
            );

            return $this->sendResponse(MemberResource::collection($members), 'Members fetched successfully.');
        } catch (\Illuminate\Database\QueryException $qe) {
            // Database query error
            \Log::error('DB Query Error fetching members', [
                'user_id' => $user->id ?? null,
                'error' => $qe->getMessage(),
                'trace' => $qe->getTraceAsString()
            ]);
            return $this->sendError('Database query error: ' . $qe->getMessage(), [], 500);
        } catch (Exception $e) {
            // Other errors
            \Log::error('Error fetching members', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Something went wrong while fetching members. Contact your administrator.', [], 500);
        }
    }
    
    public function show($identifier) {
        $identifier = trim($identifier);

        $member = Members::with([
            'company',
            'planLink.benefit',
            'planLink.planBuckets',
            'bankDetails'
        ])
        ->where(function ($query) use ($identifier) {
            if (is_numeric($identifier)) {
                $query->where('id', $identifier);
            }
    
            $query->orWhere('flexicare_id', $identifier);
        })
        ->first();
    
        if (!$member) {
            return $this->sendError('Member not found.', [], 404);
        }
            
    
        $member = new MemberProfileResource($member);
        return $this->sendResponse($member, 'Member fetched successfully.');
    }

    public function search(Request $request)
    {
        $user = auth()->user();
        $q = $request->query('q');

        // Step 1: Get company codes the user has access to
        $accessibleCompanyCodes = DB::table('user_company_accesses')
            ->join('companies', 'user_company_accesses.company_id', '=', 'companies.id')
            ->where('user_company_accesses.user_id', $user->id)
            ->pluck('companies.code');

        // Step 2: Filter members by company access and search query
        $results = Members::select(
                'id',
                'flexicare_id',
                'email',
                'first_name',
                'last_name',
                DB::raw("CONCAT(first_name, ' ', last_name) as name")
            )
            ->whereIn('company_code', $accessibleCompanyCodes)
            ->where(function ($query) use ($q) {
                $query->where('flexicare_id', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"]);
            })
            ->limit(10)
            ->get();

        return response()->json($results);
        
    }


    public function store(Request $request) {
        #Validatons
        $input_request = $request->all();

        if (!empty($input_request['fsa_amount'])) {
            $input_request['fsa_amount'] = str_replace(',', '', $input_request['fsa_amount']);
        }

        $validator = Validator::make($input_request, [
            'flexicare_id' => 'required|unique:members,flexicare_id',
            'company_code' => 'required|exists:companies,code',
            'first_name' => 'required',
            'last_name' => 'required',
            'member_classification' => 'required|in:child,employee,spouse,mother,father',
            'employee_no' => 'nullable|unique:members,employee_no',
            'gender' => 'nullable',
            'email' => 'required|email|unique:members,email',
            'member_type' => 'required|in:principal,dependent',
            'date_endorsed' => 'required|date',
            'enrollment_date' => 'required|date',
            'salary_grade' => 'nullable',
            'deactivation_date' => 'nullable|date',
            

            // ✅ Plan validation
            'reimbursement_plan.*' => 'nullable|exists:benefits,code',
            'choicepot_plan.*' => 'nullable|exists:benefits,code',
            'fsa_plan.*' => 'nullable|exists:benefits,code',

            // ✅ Bank details validation
            'bank_name' => 'nullable|string|max:150',
            'account_name' => 'nullable|string|max:150',
            'account_number' => 'nullable|string|max:50',
        ]);

        $validator->after(function ($validator) use ($input_request) {
            $hasAnyPlan =
                !empty($input_request['reimbursement_plan']) ||
                !empty($input_request['choicepot_plan']) ||
                !empty($input_request['fsa_plan']);
        
            if (!$hasAnyPlan) {
                $validator->errors()->add(
                    'plan_selection',
                    'At least one plan (Reimbursement, Choicepot, or FSA) must be selected.'
                );
            }
        });
        $validator->sometimes('fsa_amount', 'required|numeric|min:1', function ($input) {
            return !empty($input->fsa_plan); // only validate when fsa_plan is not empty
        });
        
        if ($validator->fails()) {
            return $this->sendError('Validator Error.', $validator->errors());
        }
        # END of validation
            
        DB::beginTransaction();
        try {
            #members model
            $member = Members::create([
                'flexicare_id' => $request->flexicare_id,
                'company_code' => $request->company_code,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'middle_name' => $request->middle_name,
                'suffix' => $request->suffix,
                'payee_code' => $request->payee_code,
                'member_classification' => $request->member_classification,
                'employee_no' => $request->employee_no,
                'birthdate' => $request->birthdate,
                'gender' => $request->gender,
                'civil_status' => $request->civil_status,
                'email' => $request->email,
                'position' => $request->position,
                'salary_grade' => $request->salary_grade,
                'date_hired' => $request->date_hired,
                'division' => $request->division,
                'member_type' => $request->member_type,
                'principal_id' => $request->principal_id,
                'date_endorsed' => $request->date_endorsed,
            ]);

            # Create Bank Detail
            $member->bankDetails()->create([
                'bank_name' => $request->bank_name,
                'account_name' => $request->account_name,
                'account_number' => $request->account_number,
            ]);

            # ✅ 2. Gather all selected plans
            $selectedPlans = collect();
            if (!empty($request->reimbursement_plan)) {
                $selectedPlans = $selectedPlans->merge($request->reimbursement_plan);
            }
            if (!empty($request->choicepot_plan)) {
                $selectedPlans = $selectedPlans->merge($request->choicepot_plan);
            }
            if (!empty($request->fsa_plan)) {
                $selectedPlans = $selectedPlans->merge($request->fsa_plan);
            }
            $selectedPlans;

            foreach ($selectedPlans as $planCode) {
                $benefit_id = Benefit::select('id')->where('code', $planCode)->first();

                if (!$benefit_id) {
                    throw new Exception("Benefit not found for code: $planCode");
                }

                # Find benefits using plan_code;
                $benefit = new BenefitsController(); 
                $benefit = $benefit->show($benefit_id->id)->getData();
                $expiration_date =  $benefit->data->periods->expiration_date;
                $benefit_type = $benefit->data->type;
                $benefit_period = BenefitPeriod::where('benefit_id', $benefit_id->id)
                    ->where('effectivity_date', '<=', $input_request['enrollment_date'])
                    ->where('expiration_date', '>=', $input_request['enrollment_date'])
                    ->where('status', 'active')
                    ->where('is_current', true)
                    ->first();

                $benefit_data = Benefit::where('code', $planCode)->first();
                if (is_null($benefit_period)) {
                    throw new Exception("Benefit period not found for benefit: {$benefit_data->name}");
                }

                #MemberPlanLink model
                $createdPlanLink = $member->planLink()->createMany([
                    [
                        'member_id' => $request->member_id,
                        'benefit_period_id' => $benefit_period->id,
                        'enrollment_date' => $request->enrollment_date,
                        'valid_until' => $expiration_date,
                        'status' => 'active'
                    ]
                ]);
                $createdPlanLinkId = $createdPlanLink->pluck('id')->first();

                 #MemberPlanBuckets
                switch ($benefit_type) {
                    case 'reimbursement':
                        // Insert Uflex
                        $memberPlanLinkId = $createdPlanLinkId;
                        $coverageType = 'uflex';
                        $allocatedLimit = $benefit->data->uflex;
                        $usedLimit = 0.00;
                        $remainingLimit = $benefit->data->uflex;
                        MemberPlanBucket::insertBucketData($memberPlanLinkId, $coverageType, $allocatedLimit, $usedLimit, $remainingLimit);
                        // Insert core categories
                        $coreCategories = $benefit->data->categories;
                        foreach ($coreCategories as $keyCateg) {
                            $coverageType = $keyCateg->name;
                            $allocatedLimit = $keyCateg->amount;
                            $usedLimit = 0.00;
                            $remainingLimit = $keyCateg->amount;
                            MemberPlanBucket::insertBucketData($memberPlanLinkId, $coverageType, $allocatedLimit, $usedLimit, $remainingLimit);
                        }
                        break;

                    case 'choicepot':
                        foreach ($benefit->data->categories as $category) {
                            $memberPlanLinkId = $createdPlanLinkId;
                            MemberPlanBucket::insertBucketData($memberPlanLinkId, $category->name, $category->amount, 0.00, $category->amount);
                        }
                        break;
                    case 'fsa':
                        if (empty($request->fsa_plan)) {
                            continue 2; // skip if not part of current enrollment
                        }
                        $fsaAmount = (float) preg_replace('/[^\d.]/', '', $request->fsa_amount ?? 0);
                        foreach ($benefit->data->categories as $category) {
                            $memberPlanLinkId = $createdPlanLinkId;
                            MemberPlanBucket::insertBucketData(
                                $memberPlanLinkId,
                                $category->name,
                                $fsaAmount,
                                0.00,
                                $fsaAmount
                            );
                        }
                        break;
                    default:
                        return $this->sendError("Server Error.", 'Invalid benefit type', 400);
                }

            }
             
             // Commit the transaction if no errors occur
             DB::commit();
             return $this->sendResponse([
                 "name" => Auth::user()->email,
             ], "You've created member successfully with assigned plans.");

        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError($th->getMessage(), [], 500);
        }
    }
   
    public function update(Request $request, $id) {
        // Get all inputs
        $input_request = $request->all();
    
        // Find existing member by id
        $member = Members::find($id);
        if (!$member) {
            return $this->sendError('Member not found.', [], 404);
        }
    
        // Validation rules (unique fields exclude current member)
        $validator = Validator::make($input_request, [
            'flexicare_id' => 'required|unique:members,flexicare_id,' . $member->id,
            'company_code' => 'required|exists:companies,code',
            'first_name' => 'required',
            'last_name' => 'required',
            'member_classification' => 'required|in:child,employee,spouse,mother,father',
            'employee_no' => 'nullable|unique:members,employee_no,' . $member->id,
            'gender' => 'nullable',
            'salary_grade' => 'nullable',
            'email' => 'required|email|unique:members,email,' . $member->id,
            'member_type' => 'required|in:principal,dependent',
            'date_endorsed' => 'required|date',
            'deactivation_date' => 'nullable|date',

            // ✅ Bank details validation
            'bank_name' => 'nullable|string|max:150',
            'account_name' => 'nullable|string|max:150',
            'account_number' => 'nullable|string|max:50',
        ]);
    
        if ($validator->fails()) {
            return $this->sendError('Validator Error.', $validator->errors());
        }
        // ✅ Check if there are any changes
        $inputData = collect($input_request)->only([
            'flexicare_id', 'company_code', 'first_name', 'last_name', 'middle_name','member_classification',
            'employee_no', 'gender', 'email', 'member_type', 'date_endorsed', 'birthdate', 'suffix', 'payee_code', 'civil_status', 'position', 'date_hired', 'division', 'principal_id',
            'salary_grade', 'deactivation_date'
        ]);
        $currentData = collect($member)->only($inputData->keys());

        // ✅ Bank data (compare with existing if any)
        $bank = $member->bankDetails()->first();
        $bankDataChanged = false;

        if ($request->filled('bank_name') || $request->filled('account_name') || $request->filled('account_number')) {
            $bankDataChanged =
                !$bank ||
                $bank->bank_name !== $request->bank_name ||
                $bank->account_name !== $request->account_name ||
                $bank->account_number !== $request->account_number;
        }

        // Compare
        if ($inputData->toArray() == $currentData->toArray() && !$bankDataChanged) {
            return $this->sendError('Validator Error.', [
                'no_changes' => ['No changes detected. Please modify at least one field before saving.']
            ]);
        }
    
        try {
            // Update member details only
            $member->update([
                'flexicare_id' => $request->flexicare_id,
                'company_code' => $request->company_code,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'middle_name' => $request->middle_name,
                'suffix' => $request->suffix,
                'payee_code' => $request->payee_code,
                'member_classification' => $request->member_classification,
                'employee_no' => $request->employee_no,
                'birthdate' => $request->birthdate,
                'gender' => $request->gender,
                'civil_status' => $request->civil_status,
                'email' => $request->email,
                'position' => $request->position,
                'salary_grade' => $request->salary_grade,
                'date_hired' => $request->date_hired,
                'division' => $request->division,
                'member_type' => $request->member_type,
                'principal_id' => $request->principal_id,
                'date_endorsed' => $request->date_endorsed,
                'deactivation_date' => $request->deactivation_date,
            ]);

            // ✅ Update or Create Bank Details
            if ($request->filled('bank_name') || $request->filled('account_name') || $request->filled('account_number')) {
                $member->bankDetails()->updateOrCreate(
                    ['member_id' => $member->id],
                    [
                        'bank_name' => $request->bank_name,
                        'account_name' => $request->account_name,
                        'account_number' => $request->account_number,
                    ]
                );
            }
    
            return $this->sendResponse([
                "name" => Auth::user()->email,
            ], "Member details updated successfully.");
    
        } catch (\Throwable $th) {
            return $this->sendError("Server Error.", $th->getMessage());
        }
    }
    
    public function destroy (Request $request, $member_id) { 
        DB::beginTransaction();
        try {
            $member = Members::find($member_id);
            if (!$member) {
                return $this->sendError('Member not found.', []);
            }
            // Delete related periods and categories
            foreach ($member->planLink as $planLink) {
                // Delete associated planBuckets for this planLink
                $planLink->planBuckets()->delete();
                // Delete the planLink itself
                $planLink->delete();
            }
            $member->delete();
            // Commit the transaction if no errors occur
            DB::commit();
            return $this->sendResponse([
                "name" => Auth::user()->email,
            ], "You've deleted member successfully.");
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError("Server Error.", $th->getMessage());
        }
    }

    public function bulkUpload  (Request $request) {
        $response = [];
        $request_company = $request->company;
        $addAll = $request->addAll;
        try { 
            $header = collect(json_decode($request->header))->toArray();
            if ($this->checkFileHeader($header, $addAll)) {
                $result_data = [];
                // upload to GCS
                $file = $request->file('file');
                $file_system = new FileSystemController();
                $filename = $file_system->storeFile($file, "$request_company/member_bulk_uploads");

                JobDispatcher::dispatch(
                    (new MemberBulkUploadJob(
                        $filename, 
                        $request_company, 
                        auth()->user()->id, 
                        auth()->user()->email,
                        Str::uuid()
                    ))
                );
                // $result_data = $this->uploadBulkData($request_data, $request_company, $request->addAll);

                return $this->sendResponse($result_data, "Done bulk upload");
            }
            return $this->sendError("The file being uploaded is empty.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), []);
        }
    }

    #validate file header
    private function checkFileHeader ($header, $addAll = false) {
        $included_header = [
            "ID",
            "Last Name",
            "First Name",
            "Company",
            "JG",
            "Entry Date",
            "Email Address"
        ];

        if ($addAll === TRUE) {
            $included_header = array_merge($included_header, [
                "Bank Name",
                "Bank Account Number",
                "Bank Account Name"
            ]);
        }

        $missing_headers = array_diff($included_header, $header);
        if (!empty($missing_headers)) {
            throw new Exception("The file header is missing: " . implode(", ", $missing_headers), 1);
        }

        return true;
    }

    private function mapRequestBulkData ($input_requests) {
        // mapping of uploaded data into array
        $mapped = [];
        $exceptions = [];
        foreach ($input_requests as $input_request) {
            if (!is_null($input_request['ID'])) {
                if (!isset($input_request['First Name']) || !isset($input_request['Last Name']) || !isset($input_request['Email Address']) || !isset($input_request['Entry Date'])) {
                    $exceptions[] = $input_request;
                    continue;
                }

                $insert = [
                    'flexicare_id' => $input_request['ID'],
                    'first_name' => $input_request['First Name'],
                    'last_name' => $input_request['Last Name'],
                    'email' => $input_request['Email Address'],
                    'date_endorsed' => $input_request['Entry Date'],
                    'enrollment_date' => $input_request['Entry Date'],
                    'benefit' => ClaimsHelper::mapMemberBenefits($input_request)
                ];
                $insert['action'] = "add";
                if (isset($input_request['Bank Name'])) {
                    if ($input_request['Bank Name'] != "") {
                        $insert['bank_details'] = [
                            'bank_name' => $input_request['Bank Name'],
                            'account_number' => $input_request['Bank Account Number'],
                            'account_name' => $input_request['Bank Account Name'],
                        ];
                    }
                }
                if (isset($input_request['Add/Remove/Rehire'])) {
                    $insert['action'] = $input_request['Add/Remove/Rehire'];
                }
                if (isset($input_request['Leaving Date'])) {
                    $insert['deactivation_date'] = $input_request['Leaving Date'];
                }
                if (isset($input_request['JG'])) {
                    $insert['salary_grade'] = $input_request['JG'];
                }
                if (isset($input_request['Division'])) {
                    $insert['division'] = $input_request['Division'];
                }
                $mapped[] = $insert;
            }
        }

        return ['mapped' => $mapped, 'exceptions' => $exceptions];
    }

    /**
     * Validate Request Mapped Data
     * used for validating the mapped data before processing for bulk upload
     * @param array $mapped
     * @throws Exception
     */
    private function validateRequestMappedData(array $mapped)
    {
        $validator = Validator::make($mapped, [
            '*' => Rule::forEach(function ($value) {
                return [
                    'flexicare_id' => [
                        'required',
                        ($value['action'] === 'add' || $value['action'] === 'rehire')
                        ? ['required', Rule::unique('members', 'flexicare_id')]
                        : 'required',
                    ],
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'email' => [
                        'required',
                        'email',
                        ($value['action'] === 'add' || $value['action'] === 'rehire')
                        ? ['required', Rule::unique('members', 'email')]
                        : 'required',
                    ],
                    'date_endorsed' => 'required|date',
                    'enrollment_date' => 'required|date',
                    'deactivation_date' => 'nullable|date',
                    'salary_grade' => 'nullable|string',
                    'division' => 'nullable|string',
                    'bank_details' => 'nullable|array',
                    'bank_details.bank_name' => 'nullable|string',
                    'bank_details.account_name' => 'nullable|string',
                    'bank_details.account_number' => 'nullable|string',
                ];
            }),
        ]);

        if ($validator->fails()) {
            return $this->sendError("Validation failed for one or more records.", $validator->errors()->toArray());
        }
    }

    public function uploadBulkData ($request_data, $request_company, $addAll = false) {
        $input_requests = $request_data;
        $exception_count = 0;
        $exception_data = [];

        extract($this->mapRequestBulkData($input_requests));
        
        try {
            // validating member information
            $this->validateRequestMappedData($mapped);

            $success_count = 0;
            // Process valid records
            DB::beginTransaction();
            foreach ($mapped as $arr_member) {
                $arr_new_member = [
                    'company_code' => $request_company, 
                    'status' => 'active',
                    'flexicare_id' => $arr_member['flexicare_id'],
                    'first_name' => $arr_member['first_name'],
                    'last_name' => $arr_member['last_name'],
                    'email' => $arr_member['email'],
                    'date_endorsed' => $arr_member['date_endorsed'],
                    'enrollment_date' => $arr_member['enrollment_date'],
                    'deactivation_date' => $arr_member['deactivation_date'] ?? null,
                    'division' => $arr_member['division'] ?? null,
                    'salary_grade' => $arr_member['salary_grade'] ?? null
                ];

                if ($arr_member['action'] === 'add') {
                    $new_member = Members::create($arr_new_member);
                    $new_member->bankDetails()->create($arr_member['bank_details']);

                    // create benefits
                    $new_member->setBenefitsFromBU($arr_member['benefit']);
                } else if ($arr_member['action'] === 'remove') {
                    $obj_member = Members::where('flexicare_id', $arr_member['flexicare_id'])
                        ->where('email', $arr_member['email'])
                        ->where('status', 'active')
                        ->first();

                    if ($obj_member) {
                        $obj_member->update([
                            'status' => 'inactive',
                            'deactivation_date' => $arr_member['deactivation_date']
                        ]);

                        $obj_member->deactivateBenefitFromBU_updateTag($arr_member['benefit']);
                    }
                } else if ($arr_member['action'] === 'rehire') {
                    $obj_member = Members::where('flexicare_id', $arr_member['flexicare_id'])
                        ->where('email', $arr_member['email'])
                        ->where('status', 'inactive')
                        ->first();

                    $obj_member->update([
                        'status' => 'active',
                        'deactivation_date' => null
                    ]);

                    // create benefits
                    $obj_member->setBenefitsFromBU($arr_member['benefit']);
                } else if ($arr_member['action'] === 'update') {
                    $obj_member = Members::where('flexicare_id', $arr_member['flexicare_id'])
                        ->where('email', $arr_member['email'])
                        ->where('status', 'active')
                        ->first();

                    $obj_member->update($arr_new_member);

                    $obj_member->deactivateBenefitFromBU_updateTag($arr_member['benefit']);
                    // create benefits
                    $obj_member->setBenefitsFromBU($arr_member['benefit']);
                }
                $success_count++;
            }
            DB::commit();
            return [
                'success_count' => $success_count,
                'error_count' => count($exception_data),
                'exception_data' => $exception_data,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError($e->getMessage(), []);
        }
    }

    public function getCount(Request $request) {
        // Get the company codes the user has access to
        $accessibleCompanyCodes = DB::table('user_company_accesses')
            ->join('companies', 'user_company_accesses.company_id', '=', 'companies.id')
            ->where('user_company_accesses.user_id', auth()->user()->id)
            ->pluck('companies.code')
            ->toArray(); // Convert to array for whereIn

        // If no accessible companies, return empty response
        if (empty($accessibleCompanyCodes)) {
            return $this->sendResponse([
                'index' => 0,
                'active' => 0,
                'inactive' => 0,
            ], 'No accessible members.');
        }

        // Fetch members with relationships safely
        $membersQuery = Members::whereIn('company_code', $accessibleCompanyCodes);
        
        # revised this dashboard count 
        $data = [];
        if ($request->has('search')) {
            $membersQuery->where(function($query) use ($request) {
                $search = $request->input('search');
                $query->where('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('last_name', 'LIKE', "%{$search}%")
                      ->orWhere('flexicare_id', 'LIKE', "%{$search}%")
                      ->orWhere('gender', 'LIKE', "%{$search}%")
                      ->orWhere('employee_no', 'LIKE', "%{$search}%")
                      ->orWhere('division', 'LIKE', "%{$search}%")
                      ->orWhere('company_code', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }
        $data['index'] = $membersQuery->count();

        $data['active'] = $membersQuery->where('status', 'active')->count();
        $data['inactive'] = $membersQuery->where('status', 'inactive')->count();
        return $this->sendResponse($data, "Members dashboard count retrieved successfully.");
    } 

   public function assignBenefit(Request $request)
   {
        $request->merge([
            'fsa_allocated_amount' => str_replace(',', '', $request->fsa_allocated_amount),
        ]);
        // Validate input
        $validated = $request->validate([
            'flexicare_id' => 'required|string',
            'plan_code' => 'required|string',
            'plan_type' => 'required|string', 
            // 'enrollment_date' => 'required|date|after_or_equal:today',
            'enrollment_date' => 'after_or_equal:' . now()->startOfYear()->format('Y-m-d'),
            'fsa_allocated_amount' => 'required_if:plan_type,fsa|numeric|min:0',
        ]);

        $input_request = $request->all();
        
        $member = Members::where('flexicare_id', $input_request['flexicare_id'])->firstOrFail(); 

         # Find benefits using plan_code;
         $benefit_id = Benefit::select('id')->where('code', $request->plan_code)->first(); 
         $benefit_period = BenefitPeriod::where('benefit_id', $benefit_id->id)
            ->where('effectivity_date', '<=', $input_request['enrollment_date'])
            ->where('expiration_date', '>=', $input_request['enrollment_date'])
            ->where('status', 'active')
            ->where('is_current', true)
            ->first();
         $benefit = new BenefitsController(); 
         $benefit = $benefit->show($benefit_id->id)->getData();
         $expiration_date =  $benefit->data->periods->expiration_date;
         $benefit_type = $benefit->data->type;

         #Plan link validation
         $validationResult = $this->validateDuplicateActivePlan($member->id, $benefit_period->id);
         if ($validationResult['error']) {
             return $validationResult['response']; // early return with error
         }
         # Process valid records
         try {
          
            DB::beginTransaction();
            
            # Create plan link
            $createdPlanLink = $member->planLink()->create([
                'member_id' => $member->id,
                'benefit_period_id' => $benefit_period->id,
                'enrollment_date' => $input_request['enrollment_date'],
                'valid_until' => $expiration_date,
                'status' => 'active'
            ]);

             # Create plan buckets based on benefit type
             switch ($benefit_type) {
                case 'reimbursement':
                    MemberPlanBucket::insertBucketData($createdPlanLink->id, 'uflex', $benefit->data->uflex, 0.00, $benefit->data->uflex);
                    foreach ($benefit->data->categories as $category) {
                        MemberPlanBucket::insertBucketData($createdPlanLink->id, $category->name, $category->amount, 0.00, $category->amount);
                    }
                    break;
                case 'choicepot':
                    foreach ($benefit->data->categories as $category) {
                        MemberPlanBucket::insertBucketData($createdPlanLink->id, $category->name, $category->amount, 0.00, $category->amount);
                    }
                    break;
                case 'fsa':
                    $amount = preg_replace('/[^\d.]/', '', $input_request['fsa_allocated_amount']);
                    $fsaAmount = floatval($amount);

                    foreach ($benefit->data->categories as $category) {
                        MemberPlanBucket::insertBucketData($createdPlanLink->id, $category->name, $fsaAmount, 0.00, $fsaAmount);
                    }
                    break;
                        
                    default:
                        throw new Exception('Invalid benefit type.');
                }

            DB::commit();

            return $this->sendResponse([
                "name" => Auth::user()->email,
            ], "You've created member plan successfully.");

            } catch (\Throwable $th) {
                DB::rollBack();
                return $this->sendError(
                    'Failed to create member plan link.',
                    [
                        'errors' => $th->getMessage(),
                        'input' => $input_request,
                    ],
                    500
                );
            }

   }

   protected function validateDuplicateActivePlan($memberId, $benefitPeriodId)
    {
        $existingPlanLink = MemberPlanLink::with(['benefit','member'])
            ->where('member_id', $memberId)
            ->where('benefit_period_id', $benefitPeriodId)
            ->where('status', 'active')
            ->first();

        if ($existingPlanLink) {
            return [
                'error' => true,
                'response' => $this->sendError(
                    'Member already has an active plan for this benefit.',
                    ['existing_plan' => $existingPlanLink],
                    422
                )
            ];
        }

        return ['error' => false];
    }

    
}
