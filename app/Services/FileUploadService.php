<?php
namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileUploadService
{
    protected $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

 public function uploadFile($file, string $folder): string
{
    // Ensure folder exists
    if (!Storage::disk('public')->exists($folder)) {
        Storage::disk('public')->makeDirectory($folder);
    }

    // Get extension
    $extension = $file->getClientOriginalExtension();
    if (empty($extension) || $extension === 'tmp') {
        $mimeType = $file->getMimeType();
        $extension = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    $filename = time() . '_' . uniqid() . '.' . $extension;

    // Resize image
    $image = $this->imageManager->read($file)->scale(width: 600, height: 600);

    // Encode
    $encoded = match($extension) {
        'png' => $image->toPng(quality: 80),
        'gif' => $image->toGif(),
        'webp' => $image->toWebp(quality: 80),
        default => $image->toJpeg(quality: 80),
    };

    $relativePath = $folder . '/' . $filename;

    // Store file in public disk
    Storage::disk('public')->put($relativePath, (string) $encoded);

    // Return public URL path
    return '/storage/' . $relativePath;
}

}