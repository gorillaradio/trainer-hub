<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Group extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'name', 'description', 'color', 'monthly_fee_amount',
    ];

    protected $casts = [
        'monthly_fee_amount' => 'integer',
    ];

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'group_student')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
