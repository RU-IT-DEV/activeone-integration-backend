<?php

namespace App\Http\Controllers\Api;

use App\Dispatchers\JobDispatcher;
use App\Http\Controllers\Api\BaseController as BaseController;
use App\Jobs\ProcessDeactivateBenefitPeriod;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Auth;
use Illuminate\Validation\Rule;
use Validator;
use App\Models\Benefit;
use App\Models\BenefitCategories;
use App\Models\BenefitCategorieOptions;
use App\Models\BenefitPeriod;
use App\Models\MemberPlanLink;
use App\Http\Resources\BenefitResource;

class BenefitsController  extends BaseController
{
    public function index() {
        $user = auth()->user();
        // Get IDs of companies the user has access to
        $accessibleCompanyIds = \DB::table('user_company_accesses')
            ->where('user_id', $user->id)
            ->pluck('company_id');
        // Filter benefits by user's accessible company IDs
        $benefits = Benefit::whereIn('company_id', $accessibleCompanyIds)
            ->withCount('memberPlanLinks')
            ->orderBy('id', 'desc')
            ->get();

        $today = now();
        BenefitPeriod::where('expiration_date', '<', $today)
            ->update([
                'status' => 'inactive',
                'is_current' => false
            ]);
        
        return $this->sendResponse(BenefitResource::collection($benefits), 'Benefits fetched successfully.');

    }

    public function show($benefit_id) {
        $query = Benefit::query();
    
        // Paginate the results
        $benefit = $query->with([
            'company',
            'periods', 
            'categories','categoryOptions'])
         ->findOrFail($benefit_id);
        $benefit = new BenefitResource($benefit);
        return $this->sendResponse($benefit, 'Benefit fetched successfully.');
    }

    public function store(Request $request) {
        #Validatons
        $input_request = $request->all();
        
        $request->uflex = $request->uflex ?? 0;

        switch(strtolower($request->type)) {
            case 'reimbursement':
                $validator = Validator::make($input_request, [
                    'code' => 'required|unique:benefits,code',
                    'company_id' => 'required',
                    'type' => 'required',
                    'name' => 'required',
                    'description' => 'required',
                    'period_start' =>'required|date',
                    'period_end' => 'required|date|after:period_start',
                    'categories.*.name' => 'required|string',
                    'categories.*.amount' => 'required|numeric',
                    'uflex' => 'numeric'
                ]);
                break;
            case 'choicepot':
                $validator = Validator::make($input_request, [
                    'code' => 'required|unique:benefits,code',
                    'company_id' => 'required',
                    'type' => 'required',
                    'name' => 'required',
                    'description' => 'required',
                    'period_start' =>'required|date',
                    'period_end' => 'required|date|after:period_start',
                    'categories.*.name' => 'required|string',
                    'categories.*.amount' => 'required|numeric',
                ]);
                break;
            case 'fsa':
                $validator = Validator::make($input_request, [
                    'code' => 'required|unique:benefits,code',
                    'company_id' => 'required',
                    'type' => 'required',
                    'name' => 'required',
                    'description' => 'required',
                    'period_start' =>'required|date',
                    'period_end' => 'required|date|after:period_start',
                ]);
                break;
            default:
            break;
        }
        
        if ($validator->fails()) {
            return $this->sendError('Validator Error.', $validator->errors());
        }

        DB::beginTransaction();
        try {
            #benefit model
            $benefit = Benefit::create([
                'code' => $request->code,
                'company_id' => $request->company_id,
                'type' => strtolower($request->type),
                'name' => $request->name,
                'description' => $request->description,
                'uflex' => $request->uflex
            ]);
            #benefitPeriod model
            $status = $this->generateeStatusBasedOnDate($request->period_start, $request->period_end);
            $createdPeriods = $benefit->periods()->createMany([
                [
                    'status' => $status,
                    'effectivity_date' => $request->period_start,
                    'expiration_date' => $request->period_end,
                    'is_current' => true,
                ]
            ]);

            #benefitCategories model
            if (!empty($request->categories)) {
                $benefit->categories()->createMany($request->categories);
            }
         
            #benefitCategories model
            if (!empty($request->sub_categories)) {
                $benefit->categoryOptions()->createMany($request->sub_categories);
            }

            // Commit the transaction if no errors occur
            DB::commit();
            foreach ($createdPeriods as $key => $period) {
                $expiration = Carbon::parse($period->expiration_date)->endOfDay();
                if ($period <= Carbon::now()->addHours(720)) {
                    JobDispatcher::dispatch(
                        new ProcessDeactivateBenefitPeriod($period),
                        $expiration
                    );
                }
            }

             return $this->sendResponse([
                "name" => Auth::user()->email,
             ], "You've created benefit successfully.");
        } catch (\Throwable $th) {
            DB::rollBack();
            $data = [];
            $message = "Something went wrong while processing your request. Please contact your administrator.";
            if (json_decode($message, true)) {
                $data = json_decode($message, true);
                $message = $data['message'];
            }
            return $this->sendError($message, $data);
        }
    }

    public function update(Request $request, $benefit_id)
    {
        $data = $request->all();

        // ✅ 1. Base rules
        $rules = [
            'code' => 'required|unique:benefits,code,' . $benefit_id,
            'company_id' => 'required',
            'type' => 'required|string',
            'name' => 'required|string',
            'description' => 'required|string',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
        ];

        // ✅ 2. Type-based extra rules
        $typeRules = match (strtolower($request->type)) {
            'reimbursement' => [
                'categories.*.name' => 'required|string',
                'categories.*.amount' => 'required|numeric',
                'uflex' => 'nullable|numeric',
            ],
            'choicepot' => [
                'categories.*.name' => 'required|string',
                'categories.*.amount' => 'required|numeric',
            ],
            default => [],
        };

        $validator = Validator::make($data, array_merge($rules, $typeRules));

        if ($validator->fails()) {
            return $this->sendError('Validator Error.', $validator->errors());
        }

        // ✅ 3. Get existing record (with relations)
        $benefit = Benefit::with(['periods' => fn($q) => $q->where('is_current', true), 'categories', 'categoryOptions'])
            ->find($benefit_id);

        if (!$benefit) {
            return $this->sendError('Benefit not found.', []);
        }

        // ✅ 4. Prepare comparable data
        $current = [
            'code' => $benefit->code,
            'company_id' => $benefit->company_id,
            'type' => $benefit->type,
            'name' => $benefit->name,
            'description' => $benefit->description,
            'uflex' => $benefit->uflex,
            'period_start' => optional($benefit->periods->first())->effectivity_date,
            'period_end' => optional($benefit->periods->first())->expiration_date,
        ];

        $input = collect($data)->only(array_keys($current))->toArray();

        // ✅ Normalize function for comparing arrays
        $normalize = fn($items) => collect($items ?? [])
            ->map(fn($i) => [
                'name' => trim($i['name'] ?? ''),
                'amount' => (float) ($i['amount'] ?? 0),
            ])
            ->sortBy('name') // order-insensitive
            ->values()
            ->toArray();

        $sameMain = $input == $current;
        $sameCats = $normalize($data['categories'] ?? []) == $normalize($benefit->categories);
        $sameSubs = $normalize($data['sub_categories'] ?? []) == $normalize($benefit->categoryOptions);

        if ($sameMain && $sameCats && $sameSubs) {
            return $this->sendError('Validator Error.', [
                'no_changes' => ['No changes detected. Please modify at least one field before saving.']
            ]);
        }

        // ✅ 5. Update within transaction
        DB::beginTransaction();
        try {
            $benefit->update([
                'code' => $request->code,
                'company_id' => $request->company_id,
                'type' => strtolower($request->type),
                'name' => $request->name,
                'description' => $request->description,
                'uflex' => $request->uflex,
                'updated_by' => Auth::id(),
            ]);

            $status = $this->generateeStatusBasedOnDate($request->period_start, $request->period_end);

            $benefit->periods()->updateOrCreate(
                ['is_current' => true],
                [
                    'status' => $status,
                    'effectivity_date' => $request->period_start,
                    'expiration_date' => $request->period_end,
                    'is_current' => true,
                ]
            );

            // Replace related data
            $benefit->categories()->delete();
            $benefit->categories()->createMany($data['categories'] ?? []);

            $benefit->categoryOptions()->delete();
            $benefit->categoryOptions()->createMany($data['sub_categories'] ?? []);

            DB::commit();

            return $this->sendResponse([
                'name' => Auth::user()->email,
            ], "You've updated the benefit successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->sendError('Server Error.', $e->getMessage());
        }
    }

    public function destroy (Request $request, $benefit_id) {
        DB::beginTransaction();
        try {
            $benefit = Benefit::find($benefit_id);
            if (!$benefit) {
                return $this->sendError('Benefit not found.', []);
            }

            // Delete related periods and categories
            $benefit->periods()->delete();
            $benefit->categories()->delete();

            // Delete the benefit itself
            $benefit->delete();
            // Commit the transaction if no errors occur
            DB::commit();

            return $this->sendResponse([
                "name" => Auth::user()->email,
            ], "You've deleted benefit successfully.");
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError("Server Error.", $th->getMessage());
        }
            
    }

    public function generateeStatusBasedOnDate($start_date, $end_date) {
        $today = Carbon::today();
        $status = '';
        if ($today->between($start_date, $end_date)) {
            // Update status to 'active' if today is within the date range
            $status = 'active';
        } else {
            // Otherwise, set status to 'inactive'
            $status= 'inactive';
        }
        return $status;
    }

    public function getCount() {
        $user = auth()->user();
        // Get the current date
        $today = Carbon::today();

         // Get the company IDs the user has access to
        $accessibleCompanyIds = DB::table('user_company_accesses')
        ->where('user_id', $user->id)
        ->pluck('company_id');

        // Subquery: benefits under the user's accessible companies
        $benefitIds = DB::table('benefits')
        ->whereIn('company_id', $accessibleCompanyIds)
        ->pluck('id');

        
        // Filter BenefitPeriods by those benefit_ids
        $activeCount = DB::table('benefit_periods')
            ->whereIn('benefit_id', $benefitIds)
            ->where('status', 'active')
            ->count();

        $inactiveCount = DB::table('benefit_periods')
            ->whereIn('benefit_id', $benefitIds)
            ->where('status', 'inactive')
            ->count();

        $nearDueCount = DB::table('benefit_periods')
            ->whereIn('benefit_id', $benefitIds)
            ->where('status', 'active')
            ->whereDate('expiration_date', '>=', $today)
            ->whereDate('expiration_date', '<=', $today->copy()->addDays(90))
            ->count();

        // Return the response as JSON
        $data = [
            'active' => $activeCount,
            'inactive' => $inactiveCount,
            'neardue' => $nearDueCount,
        ];
        return $this->sendResponse($data, "Benefits dashboard count retrieved successfully.");
    }

    public function getDropdownBenefits($companyId = null) {
        $query = Benefit::select('id', 'name', 'code', 'type');
        if (!empty($companyId)) {
            $query->where('company_id', $companyId);
        }
        $benefits = $query->get();
        return $this->sendResponse($benefits, "Benefits retrieved successfully.");
    }

    public function getBenefitMemberCount($benefit_id) {
        // Fetch benefit with count of linked members
        $benefit = Benefit::withCount('memberPlanLinks')->find($benefit_id);

        if (!$benefit) {
            return $this->sendError('Benefit not found.', []);
        }

        $data = [
            'benefit_id' => $benefit->id,
            'benefit_name' => $benefit->name,
            'member_count' => $benefit->member_plan_links_count,
        ];

        return $this->sendResponse($data, 'Benefit member count retrieved successfully.');
    }
}

