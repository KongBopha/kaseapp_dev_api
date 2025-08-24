<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FarmFactory extends Factory
{
    protected $model = \App\Models\Farm::class;

    public function definition(): array
    {
        return [
            'owner_id' => \App\Models\User::inRandomOrder()->first()->id,
            'name' => $this->faker->company,
            'address' => $this->faker->address,
            'about' => $this->faker->paragraph,
            'status' => $this->faker->boolean,
            'cover' => $this->faker->imageUrl(600, 300, 'nature'),
            'logo' => $this->faker->imageUrl(100, 100, 'business'),
        ];
    }
}
