<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService{
    
    /**
     * Upload a file and return its public URL
     */
    public function uploadFile(UploadedFile $file, string $folder = 'uploads'): string
    {
        // Generate unique filename
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        // Store in public disk
        $path = $file->storeAs($folder, $filename, 'public');

        // Return public URL
        return Storage::url($path);
    }
}