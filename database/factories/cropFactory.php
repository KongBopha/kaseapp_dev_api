<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CropFactory extends Factory
{
    protected $model = \App\Models\Crop::class;

    public function definition(): array
    {
        return [
            'farm_id' => \App\Models\Farm::inRandomOrder()->first()->id,
            'product_id' => \App\Models\Product::inRandomOrder()->first()->id,
            'qty' => $this->faker->numberBetween(50,500),
            'name' => $this->faker->word,
            'image' => $this->faker->imageUrl(200, 200, 'nature'),
            'status' => $this->faker->boolean,
            'harvest_date' => $this->faker->dateTimeBetween('+1 week','+3 months'),
        ];
    }
}
