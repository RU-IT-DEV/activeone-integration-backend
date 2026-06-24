<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Controllers\Api\FileSystemController;
use App\Helper\Gemini;

class IsReceipt implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $file_system = new FileSystemController();
        $main_folder = "image_validator";
        $file_path = $file_system->filesystem($value, "ask_gemini", "is_receipt", $main_folder);
        $bucket = $file_system->getBucket();
        $file = [
            "mimeType" => $file_system->getGSObject($file_path)->info()['contentType'],
            "fileUri" => "gs://$bucket/$file_path",
        ];

        $ai = new Gemini;
        $ai_response = $ai->setAISystemInstructions([])
            ->addMessageToAI("Is file a receipt? If Yes, then reply \"Yes\" and if not, reply \"invalid request\".", $file)->request('POST');
        
        $ai_response_collection = [];
        foreach ($ai_response as $key => $candidate) {
            $ai_response_collection[] = collect($candidate['candidates'][0]['content'])->pull('parts');
        }
            
        $ai_response_text = collect($ai_response_collection)->flatten(1)->pluck('text')->implode('');
        $ai_response_json = json_decode($ai_response_text, true);

        \Log::info($ai_response_text);
        if (strtolower($ai_response_json['response']) == 'invalid request') {
            $fail('The file you uploaded is not a receipt.');
        }
    }
}
