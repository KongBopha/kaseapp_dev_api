<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\vendor>
 */
class vendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * 
     */
        protected $model = \App\Models\Vendor::class;
    public function definition(): array
    {
        return [
            'owner_id' => \App\Models\User::factory(),
            'name' => $this->faker->company(),
            'vendor_type' => $this->faker->randomElement(['retailer', 'wholesaler']),
            'address' => $this->faker->address(),
            'about' => $this->faker->paragraph(),
            'logo' => $this->faker->imageUrl(),
        ];
    }
}
