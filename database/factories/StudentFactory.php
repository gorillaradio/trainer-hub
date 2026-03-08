<?php

namespace Database\Factories;

use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->date(),
            'fiscal_code' => strtoupper(fake()->bothify('??????##?##?###?')),
            'address' => fake()->address(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'notes' => null,
            'status' => StudentStatus::Active,
            'enrolled_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => StudentStatus::Inactive]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => StudentStatus::Suspended]);
    }
}
