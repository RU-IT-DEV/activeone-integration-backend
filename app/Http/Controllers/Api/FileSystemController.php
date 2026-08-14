<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class FileSystemController extends Controller
{
    public function getSignedUrl(Request $request, FileUploadService $fileService)
    {
        $validated = $request->validate(['fileName' => 'required|string']);
        $object = $fileService->bucket()->object($validated['fileName']);
        if (!$object->exists()) return response()->json(['error' => 'File not found'], 404);

        $stream = $object->downloadAsStream();
        $content = $stream->getContents();
        $mime = $object->info()['contentType'] ?? 'application/octet-stream';

        return response($content, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline');
    }
}
