<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PreOrderFactory extends Factory
{
    protected $model = \App\Models\PreOrder::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::inRandomOrder()->first()->id,
            'crop_id' => \App\Models\Crop::inRandomOrder()->first()->id,
            'product_id' => \App\Models\Product::inRandomOrder()->first()->id,
            'qty' => $this->faker->randomFloat(2, 1, 100),
            'location' => $this->faker->address,
            'note' => $this->faker->sentence,
            'delivery_date' => $this->faker->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
            'recurring_schedule' => null,
            'status' => $this->faker->randomElement(['pending', 'partially_fulfilled', 'fulfilled', 'cancelled']),
        ];
    }
}
