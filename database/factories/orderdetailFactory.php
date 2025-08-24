<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrderDetailFactory extends Factory
{
    protected $model = \App\Models\OrderDetail::class;

    public function definition(): array
    {
        return [
            'pre_order_id' => \App\Models\PreOrder::inRandomOrder()->first()->id,
            'farm_id' => \App\Models\Farm::inRandomOrder()->first()->id,
            'crop_id' => \App\Models\Crop::inRandomOrder()->first()->id,
            'fulfilled_qty' => $this->faker->randomFloat(2,1,50),
            'agreed_price' => $this->faker->randomFloat(2,1,100),
            'description' => $this->faker->sentence,
            'offer_status' => $this->faker->randomElement(['pending','accepted','rejected']),
        ];
    }
}
