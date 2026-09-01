<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkInventoryDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'name', 'ip_address', 'latitude', 'longitude', 'health_port', 'monitor_enabled',
        'vendor', 'model', 'onu_total', 'onu_online', 'rx_power', 'customer_count', 'status', 'health_status', 'last_latency_ms',
        'last_checked_at', 'location', 'notes', 'olt_type_id', 'host', 'firmware', 'serial_no', 'web_protocol',
        'web_port', 'port', 'pon_ports', 'ge_ports', 'sfp_ports', 'sfp_plus_ports', 'connect_timeout', 'cli_timeout',
        'read_delay_ms', 'diagnostic_command', 'adapter_config', 'is_active', 'latitude', 'longitude',
    ];

    protected $casts = [
        'monitor_enabled' => 'boolean', 'is_active' => 'boolean',
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
