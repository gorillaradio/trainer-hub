<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Document extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'student_id', 'type', 'title', 'file_path',
        'delivered_at', 'expires_at', 'status', 'notes',
    ];

    protected $casts = [
        'delivered_at' => 'date',
        'expires_at' => 'date',
        'type' => DocumentType::class,
        'status' => DocumentStatus::class,
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
