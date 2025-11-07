<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class UploadProductsToCloudinary extends Command
{
    protected $signature = 'products:upload-cloudinary';
    protected $description = 'Upload all existing product images from storage to Cloudinary';

    public function handle()
    {
        $products = Product::all();
        $this->info("Found {$products->count()} products to process...\n");

foreach ($products as $product) {
    if ($product->image && !str_contains($product->image, 'res.cloudinary.com')) {
        try {
            $this->info("Uploading product ID {$product->id}...");
            $filePath = storage_path('app/public/product_images/' . basename($product->image));

            if (!file_exists($filePath)) {
                $this->error("File not found: {$filePath}");
                continue;
            }

            $uploaded = Cloudinary::upload($filePath, [
                'folder' => 'products',
                'use_filename' => true,
                'unique_filename' => false,
                'overwrite' => false,
            ]);

            $url = $uploaded->getSecurePath();
            $product->update(['image' => $url]);
            $this->info("Updated product ID {$product->id} with Cloudinary URL");
        } catch (\Exception $e) {
            $this->error("Failed product ID {$product->id}: {$e->getMessage()}");
        }
    } else {
        $this->info("Skipping product ID {$product->id} (no image or already Cloudinary)");
    }
}


        $this->info("\n All products processed successfully!");
        return 0;
    }
}
