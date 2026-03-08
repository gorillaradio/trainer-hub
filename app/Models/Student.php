<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'fiscal_code',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
        'status',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'enrolled_at' => 'date',
            'status' => StudentStatus::class,
        ];
    }

    public function enrollmentFees(): HasMany
    {
        return $this->hasMany(EnrollmentFee::class);
    }

    public function monthlyFees(): HasMany
    {
        return $this->hasMany(MonthlyFee::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function unpaidFees(): HasMany
    {
        return $this->monthlyFees()->whereNull('paid_at');
    }

    public function expiringDocuments(): HasMany
    {
        return $this->documents()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30));
    }
}
