<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkTopologyLink extends Model
{
    protected $fillable = [
        'source_key', 'target_key', 'connection_type', 'label', 'status', 'is_published', 'created_by',
    ];

    protected $casts = ['is_published' => 'boolean'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
