<?php

namespace Tests\Feature;

use App\Console\Commands\CheckDeviceWatchers;
use App\Models\DeviceWatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceWatcherHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_watcher_persists_online_status(): void
    {
        $watcher = DeviceWatcher::factory()->create([
            'host' => '127.0.0.1',
            'port' => 9,
            'enabled' => true,
            'threshold_ms' => 5000,
        ]);

        $this->artisan(CheckDeviceWatchers::class)->assertSuccessful();

        $this->assertDatabaseHas('device_watchers', [
            'id' => $watcher->id,
            'last_status' => 'down',
        ]);
    }

    public function test_disabled_watcher_is_not_checked(): void
    {
        $watcher = DeviceWatcher::factory()->create([
            'enabled' => false,
            'last_status' => 'online',
        ]);

        $this->artisan(CheckDeviceWatchers::class)->assertSuccessful();

        $this->assertDatabaseHas('device_watchers', [
            'id' => $watcher->id,
            'last_status' => 'online',
        ]);
    }

    public function test_recently_checked_watcher_respects_interval(): void
    {
        $checkedAt = now()->subSeconds(10);

        $watcher = DeviceWatcher::factory()->create([
            'enabled' => true,
            'interval_seconds' => 60,
            'last_status' => 'online',
            'last_checked_at' => $checkedAt,
        ]);

        $this->artisan(CheckDeviceWatchers::class)->assertSuccessful();

        $this->assertEquals($checkedAt->timestamp, $watcher->fresh()->last_checked_at->timestamp);
    }
}
