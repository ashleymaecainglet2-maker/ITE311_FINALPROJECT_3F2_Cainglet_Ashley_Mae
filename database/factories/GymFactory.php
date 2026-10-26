<?php

namespace Database\Factories;

use App\Models\Gym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gym>
 */
class GymFactory extends Factory
{
    protected $model = Gym::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Manila', 'Quezon City', 'Cebu City', 'Davao City', 'Makati']),
            'province' => fake()->randomElement(['Metro Manila', 'Cebu', 'Davao del Sur']),
            'phone' => '02'.fake()->numerify('########'),
            'email' => fake()->unique()->companyEmail(),
            'owner_name' => fake()->name(),
            'owner_email' => fake()->unique()->safeEmail(),
            'owner_phone' => '09'.fake()->unique()->numerify('#########'),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ];
    }
}
