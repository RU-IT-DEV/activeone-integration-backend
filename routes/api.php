<?php

use App\Http\Controllers\Api\BankDetailsController;
use App\Http\Controllers\Api\CloudTasks\ProcessJobsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthenticationController;
use App\Http\Controllers\Api\Members\AuthController;

use App\Http\Controllers\Api\Company\SettingsController;

use App\Http\Controllers\Api\NavigationsController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyAttachmentController;
use App\Http\Controllers\Api\RolesController;
use App\Http\Controllers\Api\AuditLogsController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\Api\FileSystemController;
use App\Http\Controllers\Api\BenefitCategoryOptionsController;
use App\Http\Controllers\Api\MemberController as AdminMemberController;
use App\Http\Controllers\Api\Members\MemberController;
use App\Http\Controllers\Api\BenefitsController;
use App\Http\Controllers\Api\ClaimsResponseController;
use App\Http\Controllers\Api\ClaimFilingController;
use App\Http\Controllers\Api\VlookupController;
use App\Http\Controllers\Api\MemberPlanController;
use App\Http\Controllers\Api\QRScanTrackingController;
use App\Http\Controllers\Api\Benefits\BenefitRenewalController;
use App\Http\Controllers\Api\Benefits\BenefitPeriodsController;

 
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::group(['namespace' => 'Api', 'middleware' => ['cors']], function () {
    Route::post('login', [AuthenticationController::class, 'store']);
    Route::post('logout', [AuthenticationController::class, 'destroy'])->middleware('auth:api');
    Route::get('/remember-me', [AuthenticationController::class, 'getUserDetails'])->middleware('auth:api');
    Route::post('send-member-verification-mail', [AuthController::class, 'sendVerificationMail']);
    Route::post('member/login', [AuthController::class, 'memberLogin']);
    Route::post('member/login-with-msal', [AuthController::class, 'verifyAzureToken']);
    Route::post('admin/login-with-msal', [AuthenticationController::class, 'verifyAzureToken']);
    
    Route::get('flexben/object', [FileSystemController::class, 'getSignedUrl']);
    Route::post('tasks/process-queue-job', [ProcessJobsController::class, 'queue_work']);
});

Route::middleware(['auth:api','cors'])->namespace('App\Http\Controllers\Api')->group(function () {

    Route::resource('navigations', NavigationsController::class);
    Route::patch('navigations/{navigation}/status', [NavigationsController::class, 'toggleStatus']);
    #company module
    Route::resource('company', CompanyController::class);
    Route::get('dropdown-companies', [CompanyController::class, 'getDropdownCompanies']);
    Route::get('company/attachment/download/{filepath}', [CompanyAttachmentController::class, 'download']);
    Route::get('company/contract/status/history/{company}', [CompanyController::class, 'contractStatusHistory']);
    Route::get('company/contracts/status/count', [CompanyController::class, 'contractsStatusCount']);
    Route::get('/companies/neardue', [CompanyController::class, 'getNearDueCompanies']);
    Route::post('/company/send/contract-expiration', [CompanyController::class, 'sendCompaniesContractExpiration']);
    Route::get('dropdown-companies/all', [CompanyController::class, 'getAllCompanies']);
    
    Route::prefix('company/{company}')->group(function () {
        Route::resource('attachment', CompanyAttachmentController::class);
        Route::patch('status', [CompanyController::class, 'changeContractStatus']);
        Route::post('logo', [SettingsController::class, 'updateLogo']);
        Route::patch('settings', [SettingsController::class, 'update']);
        Route::get('benefits', [CompanyController::class, 'getCompanyBenefits']);
        Route::resource('coordinators', 'CompanyCoordinatorsController');
    });
    #end of company module
    #benefits module
    Route::resource('benefits', BenefitsController::class);
    Route::prefix('benefits')->group(function () {
        Route::get('dashboard/count', [BenefitsController::class, 'getCount']);
        Route::get('{benefit_id}/member-count', [BenefitsController::class, 'getBenefitMemberCount']);
        Route::get('{benefit}/members', [BenefitRenewalController::class, 'getBenefitMembers']);
        Route::get('{benefit}/periods', [BenefitPeriodsController::class, 'show']);
        Route::patch('{benefit}/periods', [BenefitPeriodsController::class, 'update']);
        Route::post('{benefit}/renew', [BenefitRenewalController::class, 'store']);
    });
    Route::get('dropdown-benefits/{companyId}', [BenefitsController::class, 'getDropdownBenefits']);
    Route::get('benefit-category-options', [BenefitCategoryOptionsController::class, 'index']);
    #end of benfits module
    #members module
    Route::get('members/dashboard/count', [AdminMemberController::class, 'getCount']);
    Route::resource('members', AdminMemberController::class);
    Route::post('/members/bulk/upload', [AdminMemberController::class, 'bulkUpload']);
    Route::post('/members/assign-benefits', [AdminMemberController::class, 'assignBenefit']);
    Route::get('/members/{id}/active-plans', [MemberPlanController::class, 'getMemberPlans']);
    Route::get('/members/db/search/', [AdminMemberController::class, 'search']);
    #users module
    Route::resource('users', UsersController::class);
    Route::get('users/dashboard/count', [UsersController::class, 'getCount']);
    #end of users module
    #claims
    Route::get('/claims', [ClaimsResponseController::class, 'getAllClaims']);
    Route::get('/claims/v2', [ClaimsResponseController::class, 'getAllClaimsV2']);
    Route::get('/claims/{id}', [ClaimsResponseController::class, 'getClaim']);
    Route::put('/claims/{id}/adjudicate', [ClaimsResponseController::class, 'adjudicateClaim']);
    Route::put('/claims/{id}/adjudicate/v2', [ClaimsResponseController::class, 'adjudicateClaimV2']);
    Route::get('/claims/dashboard/count', [ClaimsResponseController::class, 'getCount']);
    Route::get('/claims/{id}/run/engine', [ClaimsResponseController::class, 'runAdjudicationEngine']);

    #Admin insert claim
    Route::post('/claim/store', [ClaimFilingController::class, 'storeClaim']);
    Route::put('/claim/{id}/update', [ClaimFilingController::class, 'updateClaim']);
    #end of claims module

    #Vlookup 
    Route::get('/claim-type/{type}/categories', [VlookupController::class, 'byTypeClaimCategory']);
    Route::get('/rejection-reason', [VlookupController::class, 'getRejectionReason']);
    #end fof vlookup module
    #QRCode Scan Tracking
    Route::resource('qr-scan-tracking', QRScanTrackingController::class);
    Route::get('qr-scan-tracking/claim/{claimId}', [QRScanTrackingController::class, 'getClaimDetails']);
    Route::post('qr-scan-tracking/resend-email', [QRScanTrackingController::class, 'resendEmail']);
    #End of QRCode Scan Tracking

    Route::resource('roles', RolesController::class);
    Route::get('audit-logs', [AuditLogsController::class, 'index']);
    Route::get('audit-logs/{log}', [AuditLogsController::class, 'show']);

    Route::post('members/{member}/bank', [BankDetailsController::class, 'store']);
    Route::patch('members/{member}/bank/{bank}', [BankDetailsController::class, 'update']);

    #end of benfits module
    
});

Route::middleware(['auth:member_api', 'cors'])->namespace('App\Http\Controllers\Api\Members')->group(function () {
    Route::get('member/details', 'MemberController@show');
    Route::get('claim-form-providers', 'FormProviderController@claimFormProvider');
    Route::get('benefit-category-options-details/{benefit_category_option}', 'MemberController@getBenefitCategoryOptionDetailById');
    
    Route::prefix('claim')->group(function () {
        Route::post('fill-gemini', 'ClaimFiling@setGeminiKnowledge');
        Route::post('send-ai/{plan_link}', 'ClaimFiling@sendMessagetoAI');
        Route::get('{benefit_id}', 'ClaimFiling@index')->where('benefit_id', '[0-9]+');
        Route::get('table/{benefit_id}', 'ClaimFiling@claimTable')->where('benefit_id', '[0-9]+');

        Route::post('{version}/{benefit_link}', 'ClaimFiling@store')->where([
            'version' => 'v[12]',
            'benefit_link' => '[0-9]+'
        ]);
        // More specific route first to avoid being matched by the two-segment route
        Route::get('{claim}/details', 'ClaimFiling@getClaimDetails')->where('claim', '[0-9]+');
        // Constrain claim_date to YYYY-MM-DD to avoid collision with literal 'details'
        Route::get('{benefit_id}/{claim_date}', 'ClaimFiling@claimsByDate')->where([
            'benefit_id' => '[0-9]+',
            'claim_date' => '(?:\d{4}-\d{2}-\d{2}|[Tt]oday)'
        ]);
    });

    Route::prefix('members')->group(function () {
        Route::get('{member}/benefits-usage', [MemberController::class, 'getBenefitsUsage']);
    });

    Route::post('claim-receipt-upload/{claim}', 'ClaimFiling@claimFileUpload');
    Route::get('category/{claimCategory}/sub_category', 'ClaimFiling@getSubCategory_byCategoryId');
    Route::get('my/bank-details', "BankDetailsController@show");
    Route::post('my/bank-detail', "BankDetailsController@store");
    Route::post('my/profile-image', "MemberController@updateProfileImage");

    Route::post('member-logout', 'AuthController@memberLogout');
});

Route::group(['namespace' => 'Api', 'middleware' => ['cors']], function () {
    // ... other routes ...
    Route::delete('/clear-cache', function () {
        // Your logic to clear the cache goes here
        // Example:
        \Cache::flush();
        return response()->json(['message' => 'Cache cleared successfully']);
    })->middleware('purge.auth');
});


