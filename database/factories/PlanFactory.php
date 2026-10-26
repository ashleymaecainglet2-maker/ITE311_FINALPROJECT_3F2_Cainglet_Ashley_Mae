<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'name' => fake()->randomElement(['Monthly Contract', 'Daily Walk-in', 'Student Monthly', 'Quarterly']),
            'description' => fake()->sentence(),
            'price' => fake()->randomElement([150, 1200, 1500, 4000]),
            'duration_days' => fake()->randomElement([1, 30, 90]),
            'is_active' => true,
        ];
    }
}
