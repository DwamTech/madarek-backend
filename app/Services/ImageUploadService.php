<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageUploadService
{
    protected $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Upload and optimize image
     *
     * @param  UploadedFile  $file  The file to upload
     * @param  string  $directory  The directory within storage/app/public
     * @param  int  $maxWidth  Max width (preserves aspect ratio, no upsizing)
     * @param  int  $quality  Quality (0-100)
     * @param  string  $format  Output format ('jpg', 'jpeg', 'webp', 'png')
     * @return string The stored file path
     */
    public function upload(
        UploadedFile $file,
        string $directory = 'uploads',
        int $maxWidth = 1200,
        int $quality = 75,
        string $format = 'jpg'
    ): string {
        // Normalize format
        $format = strtolower($format);
        if ($format === 'jpeg') {
            $format = 'jpg';
        }

        // Create unique filename
        $extension = $format;
        $filename = time().'_'.Str::random(10).'.'.$extension;

        // Organized storage: uploads/2024/01/filename.ext
        $path = $directory.'/'.date('Y/m');

        // Ensure directory exists
        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->makeDirectory($path);
        }

        $fullPath = $path.'/'.$filename;

        // Process image
        $image = $this->manager->read($file);

        // Resize: Max width 1200, preserve aspect ratio, prevent upsizing
        $image->scaleDown(width: $maxWidth);

        // Encode based on format
        switch ($format) {
            case 'webp':
                $encoded = $image->toWebp($quality);
                break;
            case 'png':
                $encoded = $image->toPng();
                break;
            case 'jpg':
            default:
                $encoded = $image->toJpeg($quality);
                break;
        }

        // Save to storage
        Storage::disk('public')->put($fullPath, (string) $encoded);

        return $fullPath;
    }

    /**
     * Delete image from storage
     *
     * @param  string|null  $path  Relative path in public disk
     */
    public function delete(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false;
    }
}
