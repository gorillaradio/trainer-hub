<?php

namespace App\Models;

use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\EnrollmentFeeService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Student extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone',
        'date_of_birth', 'fiscal_code', 'address',
        'phone_contact_id',
        'notes', 'enrolled_at',
        'monthly_fee_override', 'current_cycle_started_at', 'past_cycles',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'enrolled_at' => 'date:Y-m-d',
        'current_cycle_started_at' => 'date:Y-m-d',
        'monthly_fee_override' => 'integer',
        'past_cycles' => 'array',
    ];

    protected $appends = ['effective_phone', 'effective_status'];

    protected function effectivePhone(): Attribute
    {
        return Attribute::get(function () {
            if ($this->phone_contact_id && $this->relationLoaded('phoneContact') && $this->phoneContact) {
                return $this->phoneContact->phone;
            }

            if ($this->phone_contact_id) {
                return $this->phoneContact?->phone;
            }

            return $this->phone;
        });
    }

    protected function effectiveStatus(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->status === 'suspended') {
                return StudentStatus::Suspended->value;
            }

            $enrollmentService = app(EnrollmentFeeService::class);
            if ($enrollmentService->hasValidEnrollment($this)) {
                return StudentStatus::Active->value;
            }

            return StudentStatus::Pending->value;
        });
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function phoneContact(): BelongsTo
    {
        return $this->belongsTo(EmergencyContact::class, 'phone_contact_id');
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_student')
            ->using(GroupStudent::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function expiringDocuments(): HasMany
    {
        return $this->documents()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30));
    }
}
