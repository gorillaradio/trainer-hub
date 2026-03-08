<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class EnrollmentFee extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'student_id', 'amount', 'paid_at',
        'payment_method', 'academic_year', 'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'payment_method' => PaymentMethod::class,
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
