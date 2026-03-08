<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyFee extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'student_id',
        'amount',
        'due_date',
        'paid_at',
        'payment_method',
        'period',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
