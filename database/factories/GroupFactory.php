<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Group> */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'color' => fake()->hexColor(),
            'monthly_fee_amount' => fake()->randomElement([3000, 4000, 5000, 6000]),
        ];
    }
}
