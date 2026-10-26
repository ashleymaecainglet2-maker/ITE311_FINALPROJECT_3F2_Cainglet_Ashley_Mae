<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\Gym;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'name' => fake()->name(),
            'phone' => '09'.fake()->unique()->numerify('#########'),
            'email' => fake()->unique()->safeEmail(),
            'status' => MemberStatus::Active,
            'address' => fake()->streetAddress(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => '09'.fake()->numerify('#########'),
            'joined_at' => fake()->dateTimeBetween('-2 years', 'now'),
            'notes' => null,
        ];
    }
}
