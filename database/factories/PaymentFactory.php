<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'amount' => fake()->randomElement([3000, 4000, 5000, 6000]),
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => now(),
            'notes' => null,
        ];
    }
}
