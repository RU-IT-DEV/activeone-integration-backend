<?php

namespace App\Services;

use Google\Cloud\Storage\StorageClient;
use Google\Cloud\SecretManager\V1\SecretManagerServiceClient as SecretManagerClient;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class FileUploadService
{
    private function storageClient(): StorageClient
    {
        $gcsConfig = config('filesystems.disks.gcs');

        if (app()->environment('local')) {
            return new StorageClient([
                'keyFilePath' => base_path($gcsConfig['key_file_path']),
            ]);
        }

        $secretClient = new SecretManagerClient();

        $secretName = $secretClient->secretVersionName(
            env('GOOGLE_CLOUD_PROJECT_ID'),
            env('GOOGLE_CLOUD_STORAGE_SECRET_NAME'),
            'latest'
        );

        $response = $secretClient->accessSecretVersion($secretName);

        $keyFile = json_decode(
            $response->getPayload()->getData(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return new StorageClient([
            'keyFile' => $keyFile,
        ]);
    }

    public function bucket()
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

    public function filesystem($file, $id, $main_folder)
    {
        if (!$file) return null;

        try {
            $extension = $file->getClientOriginalExtension();
            $newFilename = $id . "-" . Str::random(16) . '.' . $extension;
            $folder_1 = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
            $gcsPath = "{$main_folder}/{$folder_1}/{$newFilename}";
    
            $object = $this->uploadStream(fopen($file->getPathname(), 'r'), $gcsPath, $file->getClientMimeType());
            return [
                'file_path' => $gcsPath,
                'file_name' => $newFilename
            ];
        } catch (\Throwable $th) {
            throw new \Exception($th->getMessage(), 1);
        }
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

    public function getStream(string $path)
    {
        $object = $this->bucket()->object($path);

        if (!$object->exists()) {
            throw new \Exception("File {$path} does not exist in GCS.");
        }

        return $object->downloadAsStream()->detach();
    }
}
