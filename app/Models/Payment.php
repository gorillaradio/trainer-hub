<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Payment extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'student_id', 'amount', 'payment_method', 'paid_at', 'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payment_method' => PaymentMethod::class,
        'paid_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function monthlyFees(): HasMany
    {
        return $this->hasMany(MonthlyFee::class);
    }

    public function enrollmentFees(): HasMany
    {
        return $this->hasMany(EnrollmentFee::class);
    }
}
