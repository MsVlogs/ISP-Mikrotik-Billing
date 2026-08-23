<?php

namespace App\Console\Commands;

use App\Models\DeviceWatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckDeviceWatchers extends Command
{
    protected $signature = 'app:check-device-watchers';

    protected $description = 'Check enabled network devices and persist health status and latency.';

    public function handle(): int
    {
        $watchers = DeviceWatcher::query()->where('enabled', true)->get();
        $now = now();

        foreach ($watchers as $watcher) {
            if ($watcher->last_checked_at && $watcher->last_checked_at->diffInSeconds($now) < $watcher->interval_seconds) {
                continue;
            }

            $startedAt = microtime(true);
            $ok = false;
            $error = null;
            $socket = @fsockopen($watcher->host, $watcher->port, $errno, $errorMessage, 3);

            if ($socket !== false) {
                $ok = true;
                fclose($socket);
            } else {
                $error = $errorMessage ?: "Connection failed (errno {$errno})";
            }

            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $status = $ok
                ? ($latency > $watcher->threshold_ms ? 'degraded' : 'online')
                : 'down';

            $watcher->update([
                'last_status' => $status,
                'last_latency_ms' => $latency,
                'last_checked_at' => $now,
            ]);

            Log::info('Device watcher health check completed', [
                'watcher_id' => $watcher->id,
                'name' => $watcher->name,
                'host' => $watcher->host,
                'port' => $watcher->port,
                'status' => $status,
                'latency_ms' => $latency,
                'error' => $error,
            ]);
        }

        return self::SUCCESS;
    }
}
