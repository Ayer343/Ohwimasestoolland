<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class PhotoService
{
    protected $storageDisk;
    protected $imageManager;

    public function __construct()
    {
        $this->storageDisk = config('filesystems.default', 'public');
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Upload and process a photo
     */
    public function upload(UploadedFile $file, string $folder = 'users', array $options = []): string
    {
        $options = array_merge([
            'max_width' => 800,
            'max_height' => 800,
            'quality' => 80,
            'disk' => $this->storageDisk,
            'generate_thumbnail' => false,
            'thumbnail_size' => 200,
        ], $options);

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        
        // Create folder path
        $folderPath = trim($folder, '/');
        $fullPath = "{$folderPath}/{$filename}";

        // Process and store image
        $image = $this->imageManager->read($file);
        
        // Resize if needed
        if ($image->width() > $options['max_width'] || $image->height() > $options['max_height']) {
            $image->scaleDown($options['max_width'], $options['max_height']);
        }

        // Encode and save
        $encodedImage = $image->encodeByExtension($extension, $options['quality']);
        
        Storage::disk($options['disk'])->put($fullPath, $encodedImage);

        // Generate thumbnail if requested
        if ($options['generate_thumbnail']) {
            $this->generateThumbnail($image, $folderPath, $filename, $options);
        }

        return $fullPath;
    }

    /**
     * Generate thumbnail
     */
    protected function generateThumbnail($image, string $folderPath, string $filename, array $options): void
    {
        $thumbnail = clone $image;
        $thumbnail->scaleDown($options['thumbnail_size'], $options['thumbnail_size']);
        
        $thumbnailFilename = 'thumbnail_' . $filename;
        $thumbnailPath = "{$folderPath}/thumbnails/{$thumbnailFilename}";
        
        $encodedThumbnail = $thumbnail->encodeByExtension(
            pathinfo($filename, PATHINFO_EXTENSION),
            $options['quality']
        );
        
        Storage::disk($options['disk'])->put($thumbnailPath, $encodedThumbnail);
    }

    /**
     * Delete a photo
     */
    public function delete(string $path, string $folder = 'users'): bool
    {
        if (empty($path)) {
            return false;
        }

        $disk = Storage::disk($this->storageDisk);

        // Delete main photo
        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        // Delete thumbnail if exists
        $filename = basename($path);
        $thumbnailPath = str_replace($filename, "thumbnails/thumbnail_{$filename}", $path);
        
        if ($disk->exists($thumbnailPath)) {
            $disk->delete($thumbnailPath);
        }

        return true;
    }

    /**
     * Get photo URL
     */
    public function getUrl(string $path, bool $thumbnail = false): ?string
    {
        if (empty($path)) {
            return null;
        }

        if ($thumbnail) {
            $filename = basename($path);
            $thumbnailPath = str_replace($filename, "thumbnails/thumbnail_{$filename}", $path);
            
            return Storage::disk($this->storageDisk)->url($thumbnailPath);
        }

        return Storage::disk($this->storageDisk)->url($path);
    }

    /**
     * Resize existing photo
     */
    public function resize(string $path, int $width, int $height, int $quality = 80): bool
    {
        if (!Storage::disk($this->storageDisk)->exists($path)) {
            return false;
        }

        $imageContent = Storage::disk($this->storageDisk)->get($path);
        $image = $this->imageManager->read($imageContent);
        
        $image->scaleDown($width, $height);
        
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $encodedImage = $image->encodeByExtension($extension, $quality);
        
        Storage::disk($this->storageDisk)->put($path, $encodedImage);

        return true;
    }

    /**
     * Get photo info
     */
    public function getInfo(string $path): ?array
    {
        if (!Storage::disk($this->storageDisk)->exists($path)) {
            return null;
        }

        $imageContent = Storage::disk($this->storageDisk)->get($path);
        $image = $this->imageManager->read($imageContent);

        return [
            'size' => Storage::disk($this->storageDisk)->size($path),
            'mime_type' => Storage::disk($this->storageDisk)->mimeType($path),
            'last_modified' => Storage::disk($this->storageDisk)->lastModified($path),
            'dimensions' => [
                'width' => $image->width(),
                'height' => $image->height(),
            ],
            'path' => $path,
            'url' => Storage::disk($this->storageDisk)->url($path),
        ];
    }

    /**
     * Validate photo
     */
    public function validate(UploadedFile $file, array $rules = []): array
    {
        $defaultRules = [
            'max_size' => 5120, // 5MB in KB
            'allowed_mimes' => ['jpeg', 'jpg', 'png', 'gif', 'webp'],
            'min_dimensions' => [100, 100],
            'max_dimensions' => [2000, 2000],
        ];

        $rules = array_merge($defaultRules, $rules);
        $errors = [];

        // Check file size
        if ($file->getSize() > ($rules['max_size'] * 1024)) {
            $errors[] = "File size must be less than {$rules['max_size']}KB";
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        $extension = $file->getClientOriginalExtension();
        
        $allowedMimes = $rules['allowed_mimes'];
        if (!in_array(strtolower($extension), $allowedMimes) && 
            !in_array(str_replace('image/', '', $mimeType), $allowedMimes)) {
            $errors[] = "File type must be: " . implode(', ', $allowedMimes);
        }

        // Check dimensions if it's an image
        if (str_starts_with($mimeType, 'image/')) {
            try {
                $image = $this->imageManager->read($file);
                $width = $image->width();
                $height = $image->height();

                if ($width < $rules['min_dimensions'][0] || $height < $rules['min_dimensions'][1]) {
                    $errors[] = "Image dimensions must be at least {$rules['min_dimensions'][0]}x{$rules['min_dimensions'][1]} pixels";
                }

                if ($width > $rules['max_dimensions'][0] || $height > $rules['max_dimensions'][1]) {
                    $errors[] = "Image dimensions must not exceed {$rules['max_dimensions'][0]}x{$rules['max_dimensions'][1]} pixels";
                }
            } catch (\Exception $e) {
                $errors[] = "Invalid image file";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}