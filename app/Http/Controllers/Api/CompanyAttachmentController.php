<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\Api\FileSystemController as FileSystemController;
use Illuminate\Support\Facades\DB;
use App\Models\CompanyDocuments;
use Auth;
use Google\Cloud\Storage\StorageClient;

class CompanyAttachmentController extends BaseController
{
    //
    public function index($company_id) {
        $companies_docs = CompanyDocuments::select('company_documents.*')
            ->where('company_documents.company_id', $company_id)
            ->get();

        return $this->sendResponse($companies_docs, "documents retrieved successfully.");
    }

    public function store(Request $request, $company_id) {
        $this->authorize('has-access', 'Companies-create.attachments');
        $input_request = $request->all();
        $validator = Validator::make($input_request, [
            'name' => 'required|string',
            'document_type' => 'required|string',
            'file' => 'required|file|mimes:jpg,png,pdf|max:2048', // max size 2mb
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validator Error.', $validator->errors());
        }
        #CompanyDocuments model
        $document_type = $input_request['document_type'];
        $name = $input_request['name'];
        $file = $input_request['file'];

        $filename = $file->getClientOriginalName();
        $file_system = new FileSystemController(); 
        $main_folder = "companies_attachments";
        $file_path = $file_system->filesystem($file, $document_type, $company_id,$main_folder);#save file

        DB::beginTransaction();
        try {
            #save to database documents
            $company_documents = CompanyDocuments::create([
                'company_id' => $company_id,
                'name' => $name,
                'document_type' => $document_type,
                'file_path' => $file_path,
                'filename'=>$filename,
                'status' => true,
            ]);
             // Commit the transaction if no errors occur
            DB::commit();

            $result = CompanyDocuments::where('company_id', $company_id)->get()->toArray();
            return $this->sendResponse($result, "You've uploaded file successfully.");

        } catch (\Throwable $th) {
            DB::rollBack();
            
            return $this->sendError("Server Error.", $th->getMessage());
        }
        
    }

    public function destroy (Request $request, $company_id, $document_id)
    {
        $this->authorize('has-access', 'Companies-delete.attachments');
        try {
            $delete_document = CompanyDocuments::find($document_id);
            $delete_document->delete();

            $result = CompanyDocuments::where('company_id', $company_id)->get()->toArray();
            return $this->sendResponse($result, "You've deleted document successfully.");

        } catch (\Throwable $th) {
            return $this->sendError("Server Error.", $th->getMessage());
        }
    }

    public function download ($id) {
        $this->authorize('has-access', 'Companies-edit.attachments');
        $companies_doc = CompanyDocuments::select('company_documents.file_path')
            ->where('company_documents.id', $id)
            ->first();
        //  // Extract file path and name
        // $filePath = 'storage/' . $companies_doc->file_path;
        // return response()->download($filePath);

        // Initialize the Storage Client
        $storage = new StorageClient([
            'keyFilePath' => env('GOOGLE_CLOUD_STORAGE_KEY_FILE_PATH'),
        ]);
         // Get the bucket name from environment variables
        $bucketName = env('GOOGLE_CLOUD_STORAGE_BUCKET');
        $bucket = $storage->bucket($bucketName);
        $gcsPath = $companies_doc->file_path;
         // Retrieve the object from the bucket
         $object = $bucket->object($gcsPath);
         // Check if the object exists
        if ($object->exists()) {
            // Example of using /tmp for temporary storage
            $tempPath = '/tmp/file';  // Define a temp folder path
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0777, true);  // Create the directory if it doesn't exist
            }
            // Save the file to /tmp/temp-folder
            $tempFilePath = $tempPath . basename($object->name());
            file_put_contents($tempFilePath,  $object->downloadAsString());
            return response()->download($tempFilePath)->deleteFileAfterSend(true);
        } else {
            // Handle the case where the file does not exist
            return response()->json(['message' => 'File not found'], 404);
        }

    }
}
