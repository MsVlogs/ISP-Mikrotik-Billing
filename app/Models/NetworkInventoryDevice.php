<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkInventoryDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'name', 'ip_address', 'health_port', 'monitor_enabled',
        'vendor', 'model', 'onu_total', 'onu_online', 'rx_power', 'customer_count', 'status', 'health_status', 'last_latency_ms',
        'last_checked_at', 'location', 'notes',
    ];

    protected $casts = [
        'monitor_enabled' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(NetworkInventoryHealthCheck::class);
    }
}
