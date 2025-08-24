<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = \App\Models\User::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'sex' => $this->faker->randomElement(['male','female','other']),
            'profile_url' => $this->faker->imageUrl(100, 100, 'people'),
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->unique()->phoneNumber,
            'role' => $this->faker->randomElement(['farmer','vendor']),
            'user_type' => $this->faker->numberBetween(1,3),
            'address' => $this->faker->address,
        ];
    }
}
