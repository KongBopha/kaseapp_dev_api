<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = \App\Models\Product::class;

    public function definition(): array
    {
        return [
            'owner_id' => \App\Models\User::inRandomOrder()->first()->id,
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'unit' => $this->faker->randomElement(['kg','pcs','box']),
            'image' => $this->faker->imageUrl(200, 200, 'food'),
        ];
    }
}
