<?php

namespace App\Http\Controllers\Api\Members;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Controllers\Api\FileSystemController;
use App\Models\BenefitCategoryOptions;
use App\Models\ClaimCategory;
use App\Models\ClaimSubcategory;
use Error;
use Illuminate\Http\Request;
use App\Models\MemberClaims;
use App\Models\MemberClaimsLogs;
use App\Models\Members;
use App\Models\MemberPlanLink;
use App\Helper\Gemini;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Mail\ClaimFilingMail;
use App\Rules\IsReceipt;

class ClaimFiling extends BaseController
{
    public function index ($benefit_id) {
        // first get all dates of claims        
        $dates = MemberClaims::select(\DB::raw('DATE(created_at) as date'))
            ->where('member_id', Auth::guard('member_api')->user()->id)
            ->where('member_plan_links_id', $benefit_id)
            ->groupBy('date')
            ->pluck('date');
        
        $result_dates = []; $hasToday = false;
        foreach ($dates as $key => $date) {
            if ($date == date('Y-m-d')) {
                $date = 'Today';
                $hasToday = true;
            }

            array_push($result_dates, [
                'date' => $date,
                'conversation_data' => []
            ]);
        }

        if (empty($result_dates)) {
            $result_dates = [
                [
                    'date' => 'Today',
                    'conversation_data' => []
                ]
            ];
        } else if (!$hasToday) {
            array_push($result_dates, [
                'date' => 'Today',
                'conversation_data' => []
            ]);
        }

        return $this->sendResponse($result_dates, "Successful");
    }

    public function claimsByDate ($benefit_id, $claim_date) {
        if ($claim_date == 'Today') {
            $claim_date = date('Y-m-d');
        }

        $claim_ids = MemberClaims::select('id')->where('member_plan_links_id', $benefit_id)
            ->where('member_id', Auth::guard('member_api')->user()->id)
            ->get()->pluck('id')->toArray();
        // get claims by date
        $claims = MemberClaimsLogs::with(['claim' => function ($query) {
                return $query->select('member_claims.*')
                    ->selectRaw('DATE_FORMAT(member_claims.service_date, "%M %d, %Y") as service_date');
            }])->whereDate('created_at', $claim_date)
            ->whereIn('claim_id', $claim_ids)
            ->get();
        return $this->sendResponse($claims, "Claims successfully retrieved.");
    }

    public function setGeminiKnowledge (Request $request) {
        try {
            $ai = new Gemini;
            $response = $ai->setAISystemInstructions($request->all())->request('POST');
    
            return $this->sendResponse($response, "Successful.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    public function sendMessagetoAI (Request $request, MemberPlanLink $plan_link) {
        try {
            $data = $request->all();
            $member_benefits = Members::with(['activePlanLinks' => function ($query) {
                    $query->with(['benefit', 'planBuckets']);
                }, 'inactivePlanLinks' => function ($query) {
                    $query->with(['benefit', 'planBuckets']);
                }])
                ->select('*', DB::raw("CONCAT(first_name, ' ', middle_name, ' ', last_name) AS name"))
                ->find(Auth::guard('member_api')->user()->id);

            $file = false;
            if ($request->hasFile('receipt')) {
                // save file using FileSystemController
                $file_system = new FileSystemController();
                $main_folder = "member_claims";
                $file_path = $file_system->filesystem($request->file('receipt'), "ask_gemini", "receipt", $main_folder);
                $bucket = $file_system->getBucket();
                $file = [
                    "mimeType" => $file_system->getGSObject($file_path)->info()['contentType'],
                    "fileUri" => "gs://$bucket/$file_path",
                ];
            }
            
            $ai = new Gemini;
            $ai_response = $ai->setAISystemInstructions($member_benefits)
                ->addMessageToAI($data['message'], $file)->request('POST');
            
            $ai_response_collection = [];
            foreach ($ai_response as $key => $candidate) {
                $ai_response_collection[] = collect($candidate['candidates'][0]['content'])->pull('parts');
            }
                
            $ai_response_text = collect($ai_response_collection)->flatten(1)->pluck('text')->implode('');

            \Log::info($ai_response_text);
            // check if response is processable or not
            $json_ai_response = json_decode($ai_response_text, true);
            if (isset($json_ai_response['response'])) {
                $start = strpos($json_ai_response['response'], 'DEVPROC[');
                $end = strpos($json_ai_response['response'], ']DEVPROC');
                
                if ($start !== false && $end !== false) {
                    $json_string = substr($json_ai_response['response'], $start + 8, $end - $start - 8);  // Extract JSON
                    $json_string_decoded = json_decode($json_string, true);
                    if (isset($json_string_decoded['search'])) {
                        // search for a claim
                        $logged_member = Auth::guard('member_api')->user();
                        $claim = MemberClaims::where('claim_id', $json_string_decoded['claim_id'])
                            ->where('member_id', $logged_member->id)
                            ->first();
                        if ($claim) {
                            return $this->sendResponse([
                                "response" => "Your claim # {$claim->claim_id} is {$claim->status}."
                            ], "Successful.");
                        } else {
                            return $this->sendResponse([
                                "response" => "Your claim # {$json_string_decoded['claim_id']} does not exists."
                            ], "Successful.");
                        }
                    } else {
                        // save a claim
                        $data = $json_string_decoded;

                        // using Laravel make validate, validate the value of $data {\"coverage\":\"Rice\",\"category\":\"Food\",\"amount\":null,\"service_date\":null}
                        $validate = Validator::make($data, [
                            'vendor_name' => 'required|string',
                            'vendor_address' => 'required|string',
                            'coverage' => 'required|string',
                            'category' => 'required|string',
                            'amount' => 'required|numeric|gt:0',
                            'service_date' => 'required|date_format:Y-m-d',
                            'tin_number' => 'required|string',
                        ]);

                        if ($validate->fails()) {
                            return $this->sendError($validate->errors(), 400);
                        }

                        $claim = $this->claimFiling($data, $plan_link->id, $request->file('receipt'));
                        return $this->sendResponse($claim, "Claim successfully created.");
                    }
    
                    return $this->sendError("Error", 400);
                } else {
                    return $this->sendResponse(json_decode($ai_response_text, true), "Successful.");
                }
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    private function claimFiling ($data, $plan_link_id, $file = null, $version = "v1") {
        $new_claim_data = [
            'member_id' => Auth::guard('member_api')->user()->id,
            'type' => $data['type'],
            'coverage' => $data['coverage_type'],
            'member_plan_links_id' => $plan_link_id,
            'total_amount' => $data['total_amount'],
            'status' => 'Submitted',
            'version' => $version,
        ];

        if ($version == "v1") {
            $new_claim_data = array_merge($new_claim_data, [
                'vendor_name' => $data['vendor_name'],
                'vendor_address' => $data['vendor_address'],
                'category' => $data['category'],
                'service_date' => $data['service_date'],
                'tin_number' => $data['tin_number'],
                'coverage' => $data['coverage'],
            ]);
        }

        try {
            DB::beginTransaction();

            // Save first to get the claim ID
            $claim = MemberClaims::create($new_claim_data);

            // Generate the prefixed claim
            $claim->claim_id = $this->generateUniqueClaimIdV2($claim, $data['type']);
            $claim->save();

            if ($file !== null) {
                $this->claimFileUpload($file, $claim);
            }

            if (isset($data['sub_claim'])) {
                $sub_claims = $data['sub_claim'];
                foreach ($sub_claims as $sub_claim) {
                    $claim->subClaimDetails()->create($sub_claim);
                }
            }

            $claim->claim_logs()->create([
                'claim_id' => $claim->id,
                'from' => 'user',
                'status' => 'Submitted'
            ]);

            $claim->update([
                'status' => 'Pending'
            ]);
            $claim->claim_logs()->create([
                'claim_id' => $claim->id,
                'from' => 'system',
                'status' => 'Pending'
            ]);
            $this->sendClaimMail($claim);
            DB::commit();

            return $claim;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function claimFileUpload ($files, MemberClaims $claim) {
        $file_system = new FileSystemController();
        $main_folder = "member_claims";
        $savedFiles = [];

        DB::beginTransaction();
        try {
            // save file using FileSystemController
            if (!$files) {
                // rollback
                $claim->subClaimDetails()->delete();
                $claim->delete();
                return $this->errorLog('member.claim.claimFileUpload', 'ClaimFiling')
                    ->sendError('No files uploaded.', [], 400);
            }
            $files = is_array($files) ? $files : [$files];
            $category = $claim->category ?? $claim->planLink->benefit->name;

            foreach ($files as $key => $file) {
                if (!$file) continue;
                // upaload and track path
                $file_path = $file_system->filesystem($file, $claim->claim_id, $category, $main_folder);
                $savedFiles[] = $file_path;
    
                $claim->attachments()->create([
                    'filepath' => $file_path
                ]);
            }
            $claim->claim_logs()->create([
                'claim_id' => $claim->id,
                'from' => 'user',
                'status' => 'File receipt uploaded'
            ]);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("ClaimFiling.claimFileUpload: " . $e->getMessage());
            throw new Error($e->getMessage());
        }
    }

    public function sendClaimMail ($claim) {
        try {
            $email = $claim->member->email;
            Mail::to($email)->send(new ClaimFilingMail($claim));
        } catch (\Exception $e) {
            \Log::error("ClaimFiling.sendClaimMail: " . $e->getMessage());
            // return $this->sendError($e->getMessage(), 400);
            return $this->errorLog('member.claim', 'SendClaimMail')->sendError($e->getMessage(),[], 400);
        }
    }

    public function claimTable (Request $request, $benefit_id) {
        $claims = MemberClaims::with('response', 'attachments')->where('member_plan_links_id', $benefit_id)
            ->where('member_id', Auth::guard('member_api')->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($claim) {
                $claim['response_date'] = $claim->response ? $claim->response->created_at->format('Y-m-d H:i:s') : null;
                return $claim;
            });

        if ($request->method() == 'GET') {
            if ($request->has('filter')) {
                $filters = $request->input('filter');

                $this->createLog([
                    'user_id' => null,
                    'event' => 'member.claim.filter',
                    'auditable_type' => "ClaimFiling",
                    'auditable_id' => 0,
                    'severity' => -1,
                    'summary' => Auth::guard('member_api')->user()->first_name . " Searched claims for {$benefit_id}.",
                    'new_values' => $filters
                ]);

                $default_operators = ['===', '!==', '!=', '==', '=', '<>', '>', '<', '>=', '<='];

                foreach ($filters as $filter) {
                    if (isset($filter['search']) && isset($filter['selectedColumn']) && isset($filter['condition'])) {
                        $searchValue = $filter['search'];
                        $column = $filter['selectedColumn'];
                        $condition = $filter['condition'];

                        if (in_array($condition, $default_operators)) {
                            $claims = $claims->where($column, $condition, $searchValue);
                        } else {
                            $claims = $claims->filter(function ($claim) use ($searchValue, $column, $condition) {
                                $value = $claim[$column] ?? '';
                            
                                switch ($condition) {
                                    case 'contains':
                                        return stripos($value, $searchValue) !== false;
                                    case 'not contains':
                                        return stripos($value, $searchValue) === false;
                                    case 'starts with':
                                        return stripos($value, $searchValue) === 0;
                                    case 'ends with':
                                        return str_ends_with($value, $searchValue);
                                    default:
                                        // For default comparison (e.g., equals, greater than, etc.)
                                        return $value == $searchValue;
                                }
                            });
                        }
                    } elseif (isset($filter['startDate']) && isset($filter['endDate'])) {
                        $column = $filter['selectedColumn'];
                        $startDate = $filter['startDate'];
                        $endDate = $filter['endDate'];
                        $claims = $claims->whereBetween($column, [$startDate, $endDate]);
                    }
                }
            }
        }
        $claimsArray = array_values($claims->toArray()); // Add this line after filtering/mapping is complete.
        

        return $this->sendResponse($claimsArray, "Claims successfully retrieved.");
    }

    public function getClaimDetails (MemberClaims $claim) {
        try {
            $result = $claim->subClaimDetails()->get();

            return $this->sendResponse($result, "Claim Details fetched successfully.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    public function store (Request $request, String $version, MemberPlanLink $benefit_link) {
        $coverage = $request->input('coverage');
        $benefit_type = $request->input('benefit_type');
        $amount_validation = "";
        $bucket = $benefit_link->planActiveBuckets
            ->where('id', $request->member_plan_bucket_id)
            ->first();
        $remaining_limit_value = $bucket->remaining_limit;
        if ($coverage != 'Education Aid') {
            $amount_validation = "|lte:{$remaining_limit_value}";
        }

        $formType = "{$version}{$benefit_type}";
        
        $rules = match ($formType) {
            'v2choicepot' => [
                "fields.sub_claim.*.activities_or_items" => "required|string",
                "fields.sub_claim.*.description" => "required|string",
                "fields.sub_claim.*.beneficiary" => "in:Employee,Dependent",
                "fields.sub_claim.*.relation_to_employee" => "required_if:sub_claim.*.beneficiary,true",
                "fields.sub_claim.*.category" => "required|exists:benefit_category_options,name",
                "fields.sub_claim.*.sub_category" => "required|exists:claim_subcategories,name",
                "fields.sub_claim.*.amount" => "required|gt:0{$amount_validation}",
                "fields.sub_claim.*.receipt_date" => "required|date",
                "fields.receipt" => "array",
                "fields.receipt.*" => ['required','file','mimes:jpeg,png,pdf,jpg', 'max:5048']
            ],

            'v1reimbursement' => [
                'fields.vendor_name' => 'required|string',
                'fields.vendor_address' => 'required|string',
                'fields.coverage' => 'required|string',
                'fields.category' => 'nullable|string',
                'fields.total_amount' => "required|gt:0{$amount_validation}",
                'fields.service_date' => 'required|date',
                'fields.tin_number' => 'required|string',
                "fields.receipt" => "array",
                "fields.receipt.*" => ['required','file','mimes:jpeg,png,pdf,jpg', 'max:5048']
            ],

            // Parking
            'v2reimbursement' => [
                'fields.sub_claim.*.purpose' => 'required|string',
                'fields.sub_claim.*.parking_location' => 'required|string',
                'fields.sub_claim.*.vehicle_plate_number' => 'required|string',
                'fields.sub_claim.*.vendor_name' => 'required|string',
                'fields.sub_claim.*.amount' => "required|gt:0{$amount_validation}",
                'fields.sub_claim.*.or_number' => 'required|string',
                'fields.sub_claim.*.receipt_date' => 'required|date',
                "fields.receipt" => "nullable|array",
                "fields.receipt.*" => ['file','mimes:jpeg,png,pdf,jpg', 'max:5048']
            ],

            'v2fsa' => [
                'fields.sub_claim.*.purpose' => "required|exists:benefit_category_options,name",
                'fields.sub_claim.*.vendor_name' => 'required|string',
                'fields.sub_claim.*.receipt_date' => 'required|date',
                'fields.sub_claim.*.amount' => 'required|numeric',
                "fields.receipt.*" => ['required','file','mimes:jpeg,png,pdf,jpg', 'max:5048']
            ],
        };

        $validate = Validator::make($request->all(), $rules);
        if ($validate->fails()) {
            return $this->sendError("Invalid input.", $validate->errors(), 400);
        }

        try {
            $fields = $request->fields;
            $data = [...$request->all(), ...$fields];
            $data['type'] = $benefit_type;
            $data['coverage_type'] = $bucket->coverage_type;
            
            if ($version == 'v1') {
                if ($data['category'] == NULL || $data['category'] == "null") {
                    $data['category'] = $data['coverage'];
                }
            } else {
                $sub_claims = $data['sub_claim'];
                if (gettype($data['sub_claim']) == 'string') {
                    $sub_claims = json_decode($data['sub_claim'], true);
                }
                foreach ($sub_claims as $key => &$sub_claim) {
                    // if category is null
                    if (isset($sub_claim['category'])) {
                        $sub_claim['category'] = $sub_claim['category'] == NULL || $sub_claim['category'] == "null" ? 
                            $sub_claim['coverage'] : ( is_numeric($sub_claim['category']) ? 
                                BenefitCategoryOptions::where('id', $sub_claim['category'])->first()->name :
                                $sub_claim['category']
                            );
                    }

                    if (isset($sub_claim['sub_category'])) {
                        $sub_claim['sub_category'] = is_numeric($sub_claim['sub_category']) ? ClaimSubcategory::where('id', $sub_claim['sub_category'])->first()->name : $sub_claim['sub_category'];
                    }
                }
                $data['sub_claim'] = $sub_claims;
            }

            $claim = $this->claimFiling($data, $benefit_link->id, $request->file('receipt'), $version);

            return $this->successLog('member.claim.store', 'ClaimFiling')->sendResponse($claim, "Claim successfully submitted. Refer to confirmation via email.");
        } catch (\Exception $e) {
            \Log::error("ClaimFiling.store: " . $e->getMessage());
            return $this->errorLog('member.claim.store', 'ClaimFiling')->sendError($e->getMessage(), [], 400);
        }
    }

    // function that generates unique claim ids from 00001 to 99999, 
    // get the last claim id and increment it by 1    
    private function generateUniqueClaimId() {
        $lastClaim = MemberClaims::latest('claim_id')->first();
        $lastClaimId = $lastClaim ? (int)$lastClaim->claim_id : 0;
        $newClaimId = str_pad($lastClaimId + 1, 5, '0', STR_PAD_LEFT);

        // company id - member id - benefit id - generated number = claim id
        return $newClaimId;
    }

    private function generateUniqueClaimIdV2(MemberClaims $mainClaim, string $type)
    {
        // Get prefix from config
        $prefix = config('claim.type_abbreviations')[$type] ?? 'CLM';

        // Pad the main claim's database ID to 6 digits
        $paddedId = str_pad($mainClaim->id, 6, '0', STR_PAD_LEFT);

        // Combine prefix and padded ID
        return $prefix . $paddedId;
    }

    public function getSubCategory_byCategoryId(BenefitCategoryOptions $claimCategory)
    {
        $claim_category = ClaimCategory::where('name', $claimCategory->name)
            ->where('claim_type', $claimCategory->type)
            ->first();

        $sub_categories = ClaimSubcategory::where('category_id', $claim_category->id)
            ->get()->toArray();

        return $this->sendResponse($sub_categories, "Success.");
    }


}
