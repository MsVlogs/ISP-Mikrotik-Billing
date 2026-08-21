<?php

namespace Tests\Feature;

use App\Http\Controllers\MikrotikController;
use App\Livewire\Mikrotik\TrafficMonitor;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrafficMonitorTest extends TestCase
{
    #[Test]
    public function it_does_not_poll_until_monitoring_starts(): void
    {
        $controller = Mockery::mock(MikrotikController::class);
        $controller->shouldNotReceive('getLiveTraffic');
        $this->app->instance(MikrotikController::class, $controller);

        $component = new TrafficMonitor();
        $component->selectedRouter = 'router-1';
        $component->selectedInterface = 'ether1';
        $component->poll();

        $this->assertSame(0.0, $component->rxSpeed);
        $this->assertSame(0.0, $component->txSpeed);
        $this->assertFalse($component->monitoring);
    }

    #[Test]
    public function it_tracks_peaks_and_keeps_only_the_last_60_samples(): void
    {
        $controller = Mockery::mock(MikrotikController::class);
        $controller->shouldReceive('getLiveTraffic')
            ->times(61)
            ->andReturnUsing(fn () => [
                'rx-bits-per-second' => 2000000,
                'tx-bits-per-second' => 1000000,
            ]);
        $this->app->instance(MikrotikController::class, $controller);

        $component = new TrafficMonitor();
        $component->selectedRouter = 'router-1';
        $component->selectedInterface = 'ether1';
        $component->startMonitoring();

        for ($i = 0; $i < 60; $i++) {
            $component->poll();
        }

        $this->assertTrue($component->monitoring);
        $this->assertSame(2000000.0, $component->peakRxSpeed);
        $this->assertSame(1000000.0, $component->peakTxSpeed);
        $this->assertCount(60, $component->samples);
    }

    #[Test]
    public function stopping_monitoring_prevents_future_polls(): void
    {
        $controller = Mockery::mock(MikrotikController::class);
        $controller->shouldReceive('getLiveTraffic')->once()->andReturn([
            'rx-bits-per-second' => 500000,
            'tx-bits-per-second' => 250000,
        ]);
        $this->app->instance(MikrotikController::class, $controller);

        $component = new TrafficMonitor();
        $component->selectedRouter = 'router-1';
        $component->selectedInterface = 'ether1';
        $component->startMonitoring();
        $component->stopMonitoring();
        $component->poll();

        $this->assertFalse($component->monitoring);
        $this->assertCount(1, $component->samples);
    }
}
