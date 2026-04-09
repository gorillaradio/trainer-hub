<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class MonthlyFee extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = ['student_id', 'payment_id', 'period', 'expected_amount', 'due_date', 'notes'];

    protected $casts = ['expected_amount' => 'integer', 'due_date' => 'date'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
