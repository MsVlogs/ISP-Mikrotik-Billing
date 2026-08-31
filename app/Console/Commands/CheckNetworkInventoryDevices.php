<?php

namespace App\Console\Commands;

use App\Models\NetworkInventoryDevice;
use Illuminate\Console\Command;

class CheckNetworkInventoryDevices extends Command
{
    protected $signature = 'app:check-network-inventory';
    protected $description = 'Check monitored OLT, switch and access point inventory reachability.';

    public function handle(): int
    {
        NetworkInventoryDevice::query()
            ->where('monitor_enabled', true)
            ->whereNotNull('ip_address')
            ->whereNotNull('health_port')
            ->chunkById(100, function ($devices) {
                foreach ($devices as $device) {
                    $started = microtime(true);
                    $socket = @fsockopen($device->ip_address, (int) $device->health_port, $errno, $errorMessage, 3);
                    $latency = (int) round((microtime(true) - $started) * 1000);
                    $status = $socket !== false ? 'online' : 'down';
                    if ($socket !== false) {
                        fclose($socket);
                    }

                    $device->update([
                        'health_status' => $status,
                        'last_latency_ms' => $latency,
                        'last_checked_at' => now(),
                    ]);
                }
            });

        return self::SUCCESS;
    }
}
