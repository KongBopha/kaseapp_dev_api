<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        \App\Models\User::factory(10)->create();
        \App\Models\Farm::factory(5)->create();
        \App\Models\Product::factory(10)->create();
        \App\Models\Crop::factory(10)->create();
        \App\Models\PreOrder::factory(15)->create();
        \App\Models\OrderDetail::factory(15)->create();
        \App\Models\Vendor::factory(5)->create();
    }
}
