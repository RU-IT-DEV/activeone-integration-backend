<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class FileSystemController extends Controller
{
    private function storageClient(): StorageClient
    {
        $gcsConfig = config('filesystems.disks.gcs');
        $key_file_path = '';

        if (config('app.env') == 'local') {
            $key_file_path = base_path($gcsConfig['key_file_path']);
        } else {
            $key_file_path = ".." . $gcsConfig['key_file_path'];
        }

        return new StorageClient(['keyFilePath' => $key_file_path]);
    }

    private function bucket()
    {
        return $this->storageClient()->bucket(config('filesystems.disks.gcs.bucket'));
    }

    private function uploadStream($stream, string $objectName, string $contentType)
    {
        return $this->bucket()->upload($stream, [
            'name' => $objectName,
            'metadata' => ['contentType' => $contentType],
        ]);
    }

    public function filesystem($file, $document_type, $id, $main_folder)
    {
        if (!$file) return null;

        $extension = $file->getClientOriginalExtension();
        $newFilename = Str::random(16) . '.' . $extension;
        $folder_1 = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
        $folder_2 = preg_replace('/[^a-zA-Z0-9_\-]/', '', $document_type);
        $gcsPath = "{$main_folder}/{$folder_1}/{$folder_2}/{$newFilename}";

        $object = $this->uploadStream(fopen($file->getPathname(), 'r'), $gcsPath, $file->getClientMimeType());
        return $gcsPath;
    }

    private function sanitizePdf($file)
    {
        $tempOriginal = tempnam(sys_get_temp_dir(), 'orig_pdf_');
        $file->move(dirname($tempOriginal), basename($tempOriginal));
        $tempSanitized = tempnam(sys_get_temp_dir(), 'san_pdf_');

        $cmd = [
            'gs','-sDEVICE=pdfwrite','-dSAFER','-dNOPAUSE','-dBATCH',
            '-dCompatibilityLevel=1.4','-sOutputFile='.$tempSanitized,$tempOriginal
        ];

        $process = new Process($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            \Log::error("Ghostscript error: " . $process->getErrorOutput());
            @unlink($tempOriginal);
            @unlink($tempSanitized);
            return false;
        }

        @unlink($tempOriginal);

        return new \Illuminate\Http\UploadedFile(
            $tempSanitized,
            $file->getClientOriginalName(),
            $file->getClientMimeType(),
            null,
            true
        );
    }

    private function sanitizeFilename(string $filename): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-\.& ]/', '', $filename);
        return $safe ?: 'untitled_' . time();
    }

    public function getGSObject(string $file_path)
    {
        $object = $this->bucket()->object($file_path);
        return $object;
    }

    public function getBucket()
    {
        return config('filesystems.disks.gcs.bucket');
    }

    public function storeFile($data, $prefix = 'bulk_uploads')
    {
        $bucket = $this->bucket();
        if ($data instanceof \Illuminate\Http\UploadedFile) {
            $ext = $data->getClientOriginalExtension();
            $objectName = "{$prefix}/bulk_" . Carbon::now()->format('Ymd_His') . '_' . bin2hex(random_bytes(6)) . ".{$ext}";
            $this->uploadStream(fopen($data->getRealPath(), 'r'), $objectName, $data->getClientMimeType());
            return $objectName;
        }

        $json = is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $tempPath = tempnam(sys_get_temp_dir(), 'bulk_json_');
        if ($tempPath === false) {
            throw new \Exception("Failed to create temporary file for upload.");
        }
        file_put_contents($tempPath, $json);
        $objectName = "{$prefix}/bulk_" . Carbon::now()->format('Ymd_His') . '_' . bin2hex(random_bytes(6)) . ".json";

        try {
            $this->uploadStream(fopen($tempPath, 'r'), $objectName, 'application/json');
        } finally {
            @unlink($tempPath);
        }

        return $objectName;
    }

    public function getSignedUrl(Request $request)
    {
        $validated = $request->validate(['fileName' => 'required|string']);
        $object = $this->bucket()->object($validated['fileName']);
        if (!$object->exists()) return response()->json(['error' => 'File not found'], 404);

        $stream = $object->downloadAsStream();
        $content = $stream->getContents();
        $mime = $object->info()['contentType'] ?? 'application/octet-stream';

        return response($content, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline');
    }

    public function delete(string $file_path): bool
    {
        if (empty($file_path)) return false;
        try {
            $object = $this->bucket()->object($file_path);
            if ($object->exists()) $object->delete();
            return true;
        } catch (\Throwable $e) {
            \Log::warning("FileSystemController::delete failed for {$file_path}: " . $e->getMessage());
            return false;
        }
    }

    public function uploadToGCS ($localPath, $gcsPath)
    {
        $file = fopen($localPath, 'r');
        $this->uploadStream($file, $gcsPath, 'application/octet-stream');
    }
}
