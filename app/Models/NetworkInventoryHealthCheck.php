<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkInventoryHealthCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'network_inventory_device_id', 'status', 'latency_ms', 'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(NetworkInventoryDevice::class, 'network_inventory_device_id');
    }
}
