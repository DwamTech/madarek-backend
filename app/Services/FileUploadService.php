<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Upload PDF file
     *
     * @return string Relative path for database storage
     */
    public function uploadPdf(UploadedFile $file, string $directory = 'pdfs'): string
    {
        $filename = time().'_'.Str::random(10).'.pdf';

        // Organized storage: storage/app/public/pdfs/2024/01/filename.pdf
        $folderPath = $directory.'/'.date('Y').'/'.date('m');

        $path = $file->storeAs(
            $folderPath,
            $filename,
            'public'
        );

        // Return path relative to storage/app/public (e.g., pdfs/2024/01/filename.pdf)
        return $path;
    }

    /**
     * Delete file from storage
     *
     * @param  string  $path  Relative path stored in DB
     */
    public function delete(string $path): void
    {
        // Normalize path
        $path = str_replace('\\', '/', $path);

        // Remove 'storage/' prefix if present (just in case)
        $path = preg_replace('#^/?storage/#', '', $path);

        // Remove leading slashes
        $path = ltrim($path, '/');

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
