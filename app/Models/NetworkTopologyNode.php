<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkTopologyNode extends Model
{
    protected $fillable = ['type', 'name', 'location', 'notes', 'is_published', 'created_by'];

    protected $casts = ['is_published' => 'boolean'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
