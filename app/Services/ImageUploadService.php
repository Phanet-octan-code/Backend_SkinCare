<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ImageUploadService
{
    /**
     * Resiliently store an uploaded image into public/images/{folder}
     * with multi-tier directory creation, permission self-healing, direct write fallback,
     * and clear diagnostic reporting.
     *
     * @param UploadedFile $file
     * @param string $folder Subfolder name under public/images (e.g. 'products', 'categories', 'branding')
     * @param string $prefix Filename prefix (e.g. 'prod', 'cat', 'logo')
     * @return string Relative URL path (e.g. 'images/products/prod_1731000000_abc123.jpg')
     * @throws \RuntimeException If all filesystem write attempts fail due to server permission restrictions
     */
    public static function storeImage(UploadedFile $file, string $folder, string $prefix = 'img'): string
    {
        $folder = trim($folder, '/\\');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'])) {
            $ext = 'jpg';
        }
        $fileName = $prefix . '_' . time() . '_' . uniqid() . '.' . $ext;

        // Primary target: public/images/{folder}
        $destinationDir = public_path('images/' . $folder);
        $storageDir = storage_path('app/public/' . $folder);

        // Attempt 1: Ensure directories exist with self-healing permissions
        if (!File::isDirectory($destinationDir)) {
            @File::makeDirectory($destinationDir, 0777, true, true);
        }
        @chmod($destinationDir, 0777);
        if (File::isDirectory(public_path('images'))) {
            @chmod(public_path('images'), 0777);
        }

        if (!File::isDirectory($storageDir)) {
            @File::makeDirectory($storageDir, 0777, true, true);
        }
        @chmod($storageDir, 0777);

        // Auto-link storage if missing
        if (!file_exists(public_path('storage'))) {
            try {
                \Illuminate\Support\Facades\Artisan::call('storage:link');
            } catch (\Throwable $e) {}
        }

        // Attempt 2: Standard move via Symfony into public/images
        try {
            $file->move($destinationDir, $fileName);
            // Sync to storage mirror
            @copy($destinationDir . DIRECTORY_SEPARATOR . $fileName, $storageDir . DIRECTORY_SEPARATOR . $fileName);
            return 'images/' . $folder . '/' . $fileName;
        } catch (\Throwable $moveEx) {
            Log::warning("Image move() failed on {$destinationDir}: " . $moveEx->getMessage() . ". Attempting binary stream fallback.");
        }

        // Attempt 3: Direct binary stream write via file_put_contents
        try {
            $targetFilePath = $destinationDir . DIRECTORY_SEPARATOR . $fileName;
            $fileContent = @file_get_contents($file->getRealPath());
            if ($fileContent !== false && @file_put_contents($targetFilePath, $fileContent) !== false) {
                @chmod($targetFilePath, 0666);
                @copy($targetFilePath, $storageDir . DIRECTORY_SEPARATOR . $fileName);
                return 'images/' . $folder . '/' . $fileName;
            }
        } catch (\Throwable $streamEx) {
            Log::warning("Direct file_put_contents failed: " . $streamEx->getMessage());
        }

        // Attempt 4: Storage app/public fallback if public/images is strictly root-locked
        try {
            $storageTarget = $storageDir . DIRECTORY_SEPARATOR . $fileName;
            $fileContent = $fileContent ?? @file_get_contents($file->getRealPath());
            if ($fileContent !== false && @file_put_contents($storageTarget, $fileContent) !== false) {
                @chmod($storageTarget, 0666);
                @copy($storageTarget, $destinationDir . DIRECTORY_SEPARATOR . $fileName);
                return 'storage/' . $folder . '/' . $fileName;
            }
        } catch (\Throwable $storageEx) {
            Log::error("Storage fallback write failed: " . $storageEx->getMessage());
        }

        // If all attempts failed, throw informative exception with exact server command
        $serverPath = "/var/www/html/public/images/{$folder}";
        throw new \RuntimeException(
            "Directory \"{$serverPath}\" is not writable by the web server (www-data). " .
            "Please run: sudo chown -R www-data:www-data /var/www/html/public/images && sudo chmod -R 775 /var/www/html/public/images"
        );
    }

    /**
     * Delete an existing image file if safe to do so.
     *
     * @param string|null $path
     * @param string $safePrefix Prevent deleting external or unauthorized files
     * @return bool
     */
    public static function deleteImage(?string $path, string $safePrefix = ''): bool
    {
        if (!$path || empty(trim($path))) {
            return false;
        }

        if ($safePrefix && !str_contains($path, $safePrefix)) {
            return false;
        }

        // Handle public/images path
        $fullPath = public_path($path);
        if (File::exists($fullPath)) {
            return @File::delete($fullPath);
        }

        // Handle storage/ path
        if (str_starts_with($path, 'storage/')) {
            $rel = substr($path, strlen('storage/'));
            $storagePath = storage_path('app/public/' . $rel);
            if (File::exists($storagePath)) {
                return @File::delete($storagePath);
            }
        }

        return false;
    }
}

