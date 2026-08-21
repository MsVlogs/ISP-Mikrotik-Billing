<?php

namespace App\Livewire\Mikrotik;

use App\Http\Controllers\MikrotikController;
use App\Models\RouterList;
use Livewire\Component;

class TrafficMonitor extends Component
{
    public string $selectedRouter = '';
    public string $selectedInterface = '';
    public array $interfaces = [];
    public float $rxSpeed = 0;
    public float $txSpeed = 0;
    public float $peakRxSpeed = 0;
    public float $peakTxSpeed = 0;
    public array $samples = [];
    public bool $monitoring = false;

    private const MAX_SAMPLES = 60;

    public function startMonitoring(): void
    {
        $this->monitoring = true;
        $this->poll();
    }

    public function stopMonitoring(): void
    {
        $this->monitoring = false;
    }

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup'])) {
            abort(403);
        }

        $first = RouterList::where('action', 'connected')->first();
        if ($first) {
            $this->selectedRouter = $first->router_name;
            $this->loadInterfaces();
        }
    }

    public function updatedSelectedRouter(): void
    {
        $this->resetTrafficState();
        $this->interfaces = [];
        $this->selectedInterface = '';
        if ($this->selectedRouter) {
            $this->loadInterfaces();
        }
    }

    public function updatedSelectedInterface(): void
    {
        $this->resetTrafficState();
        $this->dispatch('reset-chart');
    }

    private function resetTrafficState(): void
    {
        $this->rxSpeed = 0;
        $this->txSpeed = 0;
        $this->peakRxSpeed = 0;
        $this->peakTxSpeed = 0;
        $this->samples = [];
    }

    public function loadInterfaces(): void
    {
        try {
            $ctrl = app(MikrotikController::class);
            $this->interfaces = collect($ctrl->getInterfaces($this->selectedRouter))
                ->map(fn ($i) => $i['name'] ?? null)
                ->filter()
                ->values()
                ->toArray();

            if (count($this->interfaces) > 0 && empty($this->selectedInterface)) {
                $this->selectedInterface = collect($this->interfaces)
                    ->first(fn ($i) => str_contains($i, 'ether')) ?? $this->interfaces[0];
            }
        } catch (\Exception $e) {
            flash()->error('Load error: '.$e->getMessage());
        }
    }

    public function poll(): void
    {
        if (! $this->monitoring || ! $this->selectedRouter || ! $this->selectedInterface) {
            return;
        }

        try {
            $ctrl = app(MikrotikController::class);
            $data = $ctrl->getLiveTraffic($this->selectedRouter, $this->selectedInterface);
            $this->rxSpeed = (float) ($data['rx-bits-per-second'] ?? 0);
            $this->txSpeed = (float) ($data['tx-bits-per-second'] ?? 0);
            $this->peakRxSpeed = max($this->peakRxSpeed, $this->rxSpeed);
            $this->peakTxSpeed = max($this->peakTxSpeed, $this->txSpeed);

            $this->samples[] = [
                'at' => now()->toIso8601String(),
                'rx' => $this->rxSpeed,
                'tx' => $this->txSpeed,
            ];
            if (count($this->samples) > self::MAX_SAMPLES) {
                array_shift($this->samples);
            }

            $this->dispatch('traffic-updated',
                rx: $this->rxSpeed,
                tx: $this->txSpeed,
                peakRx: $this->peakRxSpeed,
                peakTx: $this->peakTxSpeed,
                samples: $this->samples,
            );
        } catch (\Exception $e) {
            // Keep the live monitor resilient to transient router failures.
        }
    }

    public function render()
    {
        $routers = RouterList::where('action', 'connected')->orderBy('router_name')->get();

        return view('livewire.mikrotik.traffic-monitor', compact('routers'))->layout('layouts.app');
    }
}
