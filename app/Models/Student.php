<?php

namespace App\Models;

use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Student extends Model
{
    use HasFactory, HasUuids, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone',
        'date_of_birth', 'fiscal_code', 'address',
        'phone_contact_id',
        'notes', 'status', 'enrolled_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'enrolled_at' => 'date:Y-m-d',
        'status' => StudentStatus::class,
    ];

    protected $appends = ['effective_phone'];

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
