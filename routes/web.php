<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

Route::get('/test-upload', function(Request $request) {
    try {
        // Validate that an image is uploaded
        $request->validate([
            'image' => 'required|image|max:2048', // max 2MB
        ]);

        $file = $request->file('image');
        $filename = time() . '_' . $file->getClientOriginalName();

        // Create Intervention Image instance
        $manager = new ImageManager(['driver' => 'gd']);
        $image = $manager->make($file->getRealPath());

        // Optionally resize (example: max width 800px)
        $image->resize(800, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        // Save to storage/app/public/uploads
        $path = 'uploads/' . $filename;
        Storage::disk('public')->put($path, (string) $image->encode());

        return response()->json([
            'message' => 'Image uploaded successfully!',
            'path' => Storage::url($path),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error: ' . $e->getMessage(),
        ], 500);
    }
});

