<?php

namespace Database\Factories;

use App\Models\EnrollmentFee;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EnrollmentFee> */
class EnrollmentFeeFactory extends Factory
{
    protected $model = EnrollmentFee::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'payment_id' => Payment::factory(),
            'expected_amount' => fake()->randomInt(3000, 8000),
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'notes' => null,
        ];
    }
}
