<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class EnrollmentFee extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = ['student_id', 'payment_id', 'expected_amount', 'starts_at', 'expires_at', 'notes'];

    protected $casts = ['expected_amount' => 'integer', 'starts_at' => 'date', 'expires_at' => 'date'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
