<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class GroupStudent extends Pivot
{
    use HasUuids, BelongsToTenant;

    protected $table = 'group_student';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'group_id', 'student_id', 'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];
}
