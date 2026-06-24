<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Api\FileSystemController;
use App\Http\Controllers\Api\BaseController;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;
use Auth;

class SettingsController extends BaseController
{

    public function update (Request $request, $companyId)
    {   
        $this->authorize('has-access', 'Companies-edit.settings');
        // return $request->all();
        try {
            $company = Company::findOrFail($companyId);

            $validator = Validator::make($request->all(), [
                'form_version' => 'required|string|max:50',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
                'email_version' => 'nullable|string|max:255',
                'benefit_access' => 'nullable|array',
                'support_sentence_template' => 'nullable|string',
                'support_email_sentence_template' => 'nullable|string',
                'support_emails' => 'nullable|array',
            ]);
            
            if ($validator->fails()) {
                $messages = $validator->errors()->toArray();
                return $this->sendError('Validator Error.', $messages);
            }
            $validated = $validator->validated();

            // Handle logo upload (store in storage/app/public/company_logos)
            if ($request->hasFile('logo')) {

                $document_type = 'logo';
                $file =  $request->file('logo');
                $filename = $file->getClientOriginalName();
                $file_system = new FileSystemController(); 
                $main_folder = "companies_settings";
                $file_path = $file_system->filesystem($file, $document_type, $companyId,$main_folder);#save file

                $company->logo_path = $file_path;
            }

            // Update form version
            if (isset($validated['form_version'])) {
                $company->form_version = $validated['form_version'];
            }
            //update email version
            if (isset($validated['email_version'])) {
                $company->email_version = $validated['email_version'];
            }
            // Update benefit access (store as JSON)
            if (isset($validated['benefit_access'])) {
                $company->benefit_access = json_encode($validated['benefit_access']);
            }

            if (isset($validated['support_sentence_template'])) {
                $company->support_sentence_template = $validated['support_sentence_template'];
            }

            if (isset($validated['support_emails'])) {
                $saved_support_emails = $company->support->pluck('email')->values()->toArray();
                $input_emails = [];
                foreach ($validated['support_emails'] as $value) {
                    $email = $value;
                    if (is_array($value)) {
                        $email = $value['email'];
                    }

                    if (!in_array($email, $saved_support_emails)) {
                        $company->support()->create([
                            'email' => $email,
                            'label' => $value['label']
                        ]);
                    }

                    array_push($input_emails, $email);
                }
                $intersect = array_values(array_diff($saved_support_emails, $input_emails));
                if (count($intersect) > 0) {
                    $company->support()->whereIn('email', $intersect)->delete();
                }
            }
            

            $company->save();

            return $this->sendResponse([
                "name" => Auth::user()->email,
            ], "Company settings updated successfully.");

        } catch (\Throwable $th) {
            DB::rollBack();
            
            return $this->sendError("Server Error.", $th->getMessage());
        }
        
    }
    public function updateLogo (Request $request, Company $company)
    {
        $this->authorize('has-access', 'Companies-edit.settings');
        $this->validate($request, [
            'logo_path' => "required|file|mimes:jpeg,jpg,png"
        ]);

        try {
            $companyId = $company->id;
            $filename = "";

            if ($request->hasFile('logo_path')) {
                $document_type = 'logo';
                $file =  $request->file('logo_path');
                $filename = $file->getClientOriginalName();
                $file_system = new FileSystemController(); 
                $main_folder = "companies_settings";
                $file_path = $file_system->filesystem($file, $document_type, $companyId,$main_folder);#save file

                $company->logo_path = $file_path;
            }
            $company->save();

            return $this->sendResponse([
                "logo_path" => $company->logo_path
            ], "Company logo updated successfully.");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
