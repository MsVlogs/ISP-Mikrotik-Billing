<?php

namespace App\Livewire;

use App\Models\NetworkEvent;
use App\Models\RouterList;
use Livewire\Component;

class NetworkEvents extends Component
{
    public string $search = '';
    public string $severity = '';
    public string $status = 'open';
    public string $message = '';

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup', 'network-inventory'])) {
            abort(403);
        }
    }

    public function acknowledge(int $id): void
    {
        $event = NetworkEvent::whereKey($id)->where('status', 'open')->firstOrFail();
        $event->update([
            'status' => 'acknowledged',
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
        ]);
        $this->message = 'Alert acknowledged.';
    }

    public function resolve(int $id): void
    {
        NetworkEvent::whereKey($id)->whereIn('status', ['open', 'acknowledged'])->firstOrFail()->update(['status' => 'resolved', 'resolved_by' => auth()->id(), 'resolved_at' => now()]);
        $this->message = 'Alert resolved.';
    }

    public function reopen(int $id): void
    {
        NetworkEvent::whereKey($id)->where('status', 'resolved')->firstOrFail()->update(['status' => 'open', 'acknowledged_by' => null, 'acknowledged_at' => null, 'resolved_by' => null, 'resolved_at' => null]);
        $this->message = 'Alert reopened.';
    }

    public function render()
    {
        $query = NetworkEvent::query()->with(['device', 'acknowledgedBy', 'resolvedBy'])->latest('last_seen_at')->latest('id');
        if ($this->status !== 'all') {
            $query->where('status', $this->status ?: 'open');
        }
        if ($this->severity !== '') {
            $query->where('severity', $this->severity);
        }
        $q = trim($this->search);
        if ($q !== '') {
            $like = '%'.$q.'%';
            $routerIds = RouterList::where('router_name', 'like', $like)->pluck('id')->all();
            $query->where(function ($w) use ($like, $routerIds) {
                $w->where('title', 'like', $like)
                    ->orWhere('message', 'like', $like)
                    ->orWhere('event_type', 'like', $like)
                    ->orWhereHas('device', fn ($d) => $d->where('name', 'like', $like)->orWhere('ip_address', 'like', $like));
                foreach ($routerIds as $routerId) {
                    $w->orWhere('metadata->router_id', $routerId);
                }
            });
        }
        $events = $query->paginate(25);
        $routerIds = $events->getCollection()->filter(fn ($event) => ! $event->device && data_get($event->metadata, 'router_id'))
            ->map(fn ($event) => (int) data_get($event->metadata, 'router_id'))->unique()->values();
        $routers = $routerIds->isEmpty() ? collect() : RouterList::whereIn('id', $routerIds)->get()->keyBy('id');
        $open = NetworkEvent::whereIn('status', ['open', 'acknowledged'])->count();
        $critical = NetworkEvent::whereIn('status', ['open', 'acknowledged'])->where('severity', 'critical')->count();
        $warning = NetworkEvent::whereIn('status', ['open', 'acknowledged'])->where('severity', 'warning')->count();
        $resolvedToday = NetworkEvent::where('status', 'resolved')->whereDate('updated_at', today())->count();

        return view('livewire.network-events', compact('events', 'routers', 'open', 'critical', 'warning', 'resolvedToday'))
            ->layout('layouts.app');
    }
}
