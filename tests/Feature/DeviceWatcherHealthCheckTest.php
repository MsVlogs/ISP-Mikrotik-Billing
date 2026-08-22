<?php

namespace Tests\Feature;

use App\Console\Commands\CheckDeviceWatchers;
use App\Models\DeviceWatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceWatcherHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    private function createWatcher(array $overrides = []): DeviceWatcher
    {
        return DeviceWatcher::query()->create(array_merge([
            'name' => 'Test Router',
            'router_name' => 'Test Router',
            'host' => '127.0.0.1',
            'port' => 9,
            'interval_seconds' => 60,
            'threshold_ms' => 5000,
            'enabled' => true,
        ], $overrides));
    }

    public function test_unreachable_watcher_persists_down_status(): void
    {
        $watcher = $this->createWatcher();

        $this->artisan('app:check-device-watchers')->assertSuccessful();

        $this->assertDatabaseHas('device_watchers', [
            'id' => $watcher->id,
            'last_status' => 'down',
        ]);
    }

    public function test_disabled_watcher_is_not_checked(): void
    {
        $watcher = $this->createWatcher([
            'enabled' => false,
            'last_status' => 'online',
        ]);

        $this->artisan('app:check-device-watchers')->assertSuccessful();

        $this->assertDatabaseHas('device_watchers', [
            'id' => $watcher->id,
            'last_status' => 'online',
            'last_checked_at' => null,
        ]);
    }

    public function test_recently_checked_watcher_respects_interval(): void
    {
        $checkedAt = now()->subSeconds(10);

        $watcher = $this->createWatcher([
            'interval_seconds' => 60,
            'last_status' => 'online',
            'last_checked_at' => $checkedAt,
        ]);

        $this->artisan('app:check-device-watchers')->assertSuccessful();

        $this->assertEquals($checkedAt->timestamp, $watcher->fresh()->last_checked_at->timestamp);
    }
}
