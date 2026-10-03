<?php

namespace App\Livewire;

use App\Models\NetworkInventoryDevice;
use App\Models\NetworkTopologyLink;
use App\Models\NetworkTopologyNode;
use App\Models\OltOnuCustomerMapping;
use App\Models\RouterList;
use Illuminate\Validation\Rule;
use Livewire\Component;

class NetworkTopology extends Component
{
    public string $mode = 'designer';
    public string $source_key = '';
    public string $target_key = '';
    public string $connection_type = 'fiber_core';
    public string $label = '';
    public string $capacity_mbps = '';
    public string $traffic_mbps = '';
    public string $latency_ms = '';
    public string $packet_loss = '';
    public string $fiber_core = '';
    public string $fiber_type = 'singlemode';
    public string $splitter_ratio = '';
    public string $port_reference = '';
    public string $source_port = '';
    public string $target_port = '';
    public string $input_ports = '1';
    public string $output_ports = '';
    public string $message = '';
    public string $newNodeType = 'splitter';
    public string $newNodeName = '';
    public string $newNodeLocation = '';
    public string $newNodeNotes = '';

    public function mount(string $mode = 'designer'): void
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup', 'network-inventory'])) {
            abort(403);
        }
        abort_unless(in_array($mode, ['designer', 'live'], true), 404);
        $this->mode = $mode;
    }

    public function saveLink(): void
    {
        if ($this->mode !== 'designer') abort(403);
        $nodeOptions = $this->nodeOptions();
        $data = $this->validate([
            'source_key' => ['required', 'string', Rule::in(array_keys($nodeOptions))],
            'target_key' => ['required', 'string', Rule::in(array_keys($nodeOptions)), 'different:source_key'],
            'connection_type' => ['required', Rule::in(['uplink', 'ethernet', 'fiber_core', 'splitter', 'logical_service'])],
            'label' => ['nullable', 'string', 'max:120'],
            'capacity_mbps' => ['nullable', 'integer', 'min:1'],
            'traffic_mbps' => ['nullable', 'integer', 'min:0'],
            'latency_ms' => ['nullable', 'numeric', 'min:0'],
            'packet_loss' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fiber_core' => ['nullable', 'integer', 'min:1'],
            'fiber_type' => ['nullable', Rule::in(['singlemode', 'multimode', 'drop', 'unknown'])],
            'source_port' => ['nullable', 'string', 'max:40'],
            'target_port' => ['nullable', 'string', 'max:40'],
        ]);

        NetworkTopologyLink::updateOrCreate(
            ['source_key' => $data['source_key'], 'target_key' => $data['target_key'], 'connection_type' => $data['connection_type']],
            ['label' => $data['label'] ?: null, 'capacity_mbps' => $data['capacity_mbps'] ?: null, 'traffic_mbps' => $data['traffic_mbps'] ?: null, 'latency_ms' => $data['latency_ms'] ?: null, 'packet_loss' => $data['packet_loss'] ?: null, 'fiber_core' => $data['fiber_core'] ?: null, 'fiber_type' => $data['fiber_type'] ?: null, 'source_port' => $data['source_port'] ?: null, 'target_port' => $data['target_port'] ?: null, 'status' => 'unknown', 'is_published' => false, 'created_by' => auth()->id()]
        );

        $this->reset(['source_key', 'target_key', 'label', 'capacity_mbps', 'traffic_mbps', 'latency_ms', 'packet_loss', 'fiber_core', 'source_port', 'target_port']);
        $this->fiber_type = 'singlemode';
        $this->connection_type = 'fiber_core';
        $this->message = 'Topology connection saved as a draft. Publish it to show it in Live Topology.';
    }

    public function saveNode(): void
    {
        if ($this->mode !== 'designer') abort(403);
        $data = $this->validate([
            'newNodeType' => ['required', Rule::in(['splitter', 'olt_pon', 'odf', 'rack', 'pop', 'fiber_segment'])],
            'newNodeName' => ['required', 'string', 'max:120'],
            'newNodeLocation' => ['nullable', 'string', 'max:160'],
            'newNodeNotes' => ['nullable', 'string', 'max:1000'],
            'splitter_ratio' => ['nullable', 'integer', 'min:2', 'max:64'],
            'port_reference' => ['nullable', 'string', 'max:80'],
            'input_ports' => ['nullable', 'integer', 'min:1', 'max:64'],
            'output_ports' => ['nullable', 'integer', 'min:1', 'max:64'],
        ]);
        NetworkTopologyNode::create([
            'type' => $data['newNodeType'], 'name' => trim($data['newNodeName']),
            'location' => $data['newNodeLocation'] ?: null, 'port_reference' => $this->port_reference ?: null,
            'splitter_ratio' => $data['newNodeType'] === 'splitter' && $this->splitter_ratio !== '' ? (int) $this->splitter_ratio : null,
            'input_ports' => $data['newNodeType'] === 'splitter' ? (int) ($this->input_ports ?: 1) : null,
            'output_ports' => $data['newNodeType'] === 'splitter' ? (int) ($this->output_ports ?: ($this->splitter_ratio ?: 1)) : null,
            'notes' => $data['newNodeNotes'] ?: null,
            'is_published' => false, 'created_by' => auth()->id(),
        ]);
        $this->reset(['newNodeName', 'newNodeLocation', 'newNodeNotes']);
        $this->newNodeType = 'splitter';
        $this->splitter_ratio = '';
        $this->port_reference = '';
        $this->input_ports = '1';
        $this->output_ports = '';
        $this->message = 'Topology node saved as a draft. Publish it before publishing its connections.';
    }

    public function publishNode(int $id): void
    {
        if ($this->mode !== 'designer') abort(403);
        NetworkTopologyNode::findOrFail($id)->update(['is_published' => true]);
        $this->message = 'Topology node published.';
    }

    public function unpublishNode(int $id): void
    {
        if ($this->mode !== 'designer') abort(403);
        $nodeKey = 'topology:'.$id;
        $hasPublishedLinks = NetworkTopologyLink::where('is_published', true)
            ->where(fn ($q) => $q->where('source_key', $nodeKey)->orWhere('target_key', $nodeKey))->exists();
        if ($hasPublishedLinks) {
            $this->message = 'Unpublish or remove this node’s published connections first.';
            return;
        }
        NetworkTopologyNode::findOrFail($id)->update(['is_published' => false]);
        $this->message = 'Topology node moved back to draft.';
    }

    public function deleteNode(int $id): void
    {
        if ($this->mode !== 'designer') abort(403);
        $nodeKey = 'topology:'.$id;
        NetworkTopologyLink::where('source_key', $nodeKey)->orWhere('target_key', $nodeKey)->delete();
        NetworkTopologyNode::findOrFail($id)->delete();
        $this->message = 'Topology node and its connections deleted.';
    }

    public function publish(int $id): void
    {
        if ($this->mode !== 'designer') abort(403);
        $link = NetworkTopologyLink::findOrFail($id);
        foreach ([$link->source_key, $link->target_key] as $key) {
            if (str_starts_with($key, 'topology:')) {
                $node = NetworkTopologyNode::find(substr($key, 9));
                if (! $node || ! $node->is_published) {
                    $this->message = 'Publish all custom topology nodes used by this connection first.';
                    return;
                }
            }
        }
        $link->update(['is_published' => true, 'status' => 'active']);
        $this->message = 'Connection published to Live Topology.';
    }

    public function unpublish(int $id): void
    {
        if ($this->mode !== 'designer') abort(403);
        NetworkTopologyLink::findOrFail($id)->update(['is_published' => false, 'status' => 'draft']);
        $this->message = 'Connection moved back to draft.';
    }

    public function deleteLink(int $id): void
    {
        if ($this->mode !== 'designer') abort(403);
        NetworkTopologyLink::findOrFail($id)->delete();
        $this->message = 'Topology connection deleted.';
    }

    private function nodeOptions(): array
    {
        $options = [];
        foreach (RouterList::orderBy('router_name')->get() as $router) {
            $options['router:'.$router->id] = 'MikroTik · '.$router->router_name.' ('.$router->ip_address.')';
        }
        foreach (NetworkInventoryDevice::orderBy('name')->get() as $device) {
            $options['device:'.$device->id] = strtoupper(str_replace('-', ' ', $device->type)).' · '.$device->name.' ('.($device->ip_address ?: $device->host ?: 'no IP').')';
        }
        $customNodes = NetworkTopologyNode::query()->when($this->mode === 'live', fn ($q) => $q->where('is_published', true))->orderBy('name')->get();
        foreach ($customNodes as $node) {
            $options['topology:'.$node->id] = strtoupper(str_replace('_', ' ', $node->type)).' · '.$node->name;
        }
        return $options;
    }

    private function baseNodes(): array
    {
        $nodes = [];
        foreach (RouterList::orderBy('router_name')->get() as $router) {
            $nodes[] = [
                'id' => 'router:'.$router->id, 'label' => $router->router_name, 'group' => 'router',
                'status' => $router->action === 'connected' ? 'online' : (in_array($router->action, ['disconnected', 'offline'], true) ? 'offline' : 'unknown'),
                'title' => 'MikroTik · '.($router->ip_address ?: 'No management IP'),
                'url' => route('device-manager.detail', ['kind' => 'mikrotik', 'device' => $router->id]),
            ];
        }
        foreach (NetworkInventoryDevice::orderBy('name')->get() as $device) {
            $raw = strtolower(str_replace([' ', '-'], '_', (string) ($device->health_status ?: $device->status)));
            $status = in_array($raw, ['ready', 'online', 'connected', 'up'], true) ? 'online' : (in_array($raw, ['failed', 'down', 'offline', 'unreachable'], true) ? 'offline' : 'unknown');
            $nodes[] = [
                'id' => 'device:'.$device->id, 'label' => $device->name, 'group' => $device->type,
                'status' => $status, 'title' => strtoupper($device->type).' · '.($device->ip_address ?: $device->host ?: 'No management IP'),
                'url' => route('device-manager.detail', ['kind' => $device->type === 'olt' ? 'olt' : 'device', 'device' => $device->id]),
            ];
        }
        $customNodes = NetworkTopologyNode::query()->when($this->mode === 'live', fn ($q) => $q->where('is_published', true))->orderBy('name')->get();
        foreach ($customNodes as $node) {
            $nodes[] = [
                'id' => 'topology:'.$node->id, 'label' => $node->name, 'group' => $node->type,
                'status' => 'unknown', 'title' => strtoupper(str_replace('_', ' ', $node->type)).' · '.($node->location ?: 'Location not set'),
                'url' => null,
            ];
        }
        return $nodes;
    }

    public function render()
    {
        $nodes = $this->baseNodes();
        $query = NetworkTopologyLink::query()->latest();
        if ($this->mode === 'live') $query->where('is_published', true);
        $links = $query->get();

        $graphEdges = [];
        $nodeKeys = collect($nodes)->pluck('id')->all();
        foreach ($links as $link) {
            if (! in_array($link->source_key, $nodeKeys, true) || ! in_array($link->target_key, $nodeKeys, true)) continue;
            $graphEdges[] = [
                'id' => 'link:'.$link->id, 'from' => $link->source_key, 'to' => $link->target_key,
                'label' => $link->label ?: trim((string) (($link->source_port ?: '') . (($link->source_port || $link->target_port) ? ' → ' : '') . ($link->target_port ?: str_replace('_', ' ', $link->connection_type)))),
                'connection_type' => $link->connection_type, 'source_port' => $link->source_port, 'target_port' => $link->target_port, 'arrows' => 'to',
                'capacity_mbps' => $link->capacity_mbps, 'traffic_mbps' => $link->traffic_mbps,
                'latency_ms' => $link->latency_ms, 'packet_loss' => $link->packet_loss,
                'utilization' => ($link->capacity_mbps && $link->capacity_mbps > 0) ? round(($link->traffic_mbps ?: 0) / $link->capacity_mbps * 100, 1) : null,
                'dashes' => $link->connection_type === 'logical_service',
            ];
        }

        if ($this->mode === 'live') {
            $mappings = OltOnuCustomerMapping::with(['customer', 'olt'])->latest('last_seen_at')->limit(500)->get();
            $existing = collect($nodes)->keyBy('id');
            foreach ($mappings as $mapping) {
                if (! $mapping->olt || ! $mapping->onu_id) continue;
                $onuKey = 'onu:'.$mapping->id;
                $ponRef = trim((string) ($mapping->pon_port ?: 'PON'));
                $ponKey = 'pon:'.$mapping->olt_device_id.':'.preg_replace('/[^A-Za-z0-9_.:-]/', '_', $ponRef);
                if (! $existing->has($ponKey)) {
                    $nodes[] = [
                        'id' => $ponKey, 'label' => 'PON '.$ponRef, 'group' => 'olt_pon', 'status' => 'online',
                        'title' => 'OLT PON Port '.$ponRef, 'url' => route('network-inventory.olt.customers', $mapping->olt_device_id),
                    ];
                    $existing->put($ponKey, true);
                    $oltKey = 'device:'.$mapping->olt_device_id;
                    if ($existing->has($oltKey)) $graphEdges[] = ['id'=>'auto:olt-pon:'.$ponKey,'from'=>$oltKey,'to'=>$ponKey,'label'=>$ponRef,'connection_type'=>'fiber_core','arrows'=>'to','capacity_mbps'=>null,'traffic_mbps'=>null,'utilization'=>null];
                }
                $customerName = $mapping->customer?->customer_name ?: $mapping->customer?->customer_unique_id ?: 'Unmapped ONU';
                $mappingStatus = strtolower(trim((string) $mapping->status));
                $status = in_array($mappingStatus, ['online', 'up', 'active', 'connected', 'ready'], true)
                    ? 'online'
                    : (in_array($mappingStatus, ['offline', 'down', 'failed', 'unreachable'], true) ? 'offline' : 'unknown');
                if ($mapping->last_seen_at && $mapping->last_seen_at->lt(now()->subMinutes(15)) && $status === 'online') {
                    $status = 'unknown';
                }
                $lastSeen = $mapping->last_seen_at?->format('Y-m-d H:i:s') ?: 'Not available';
                $nodes[] = [
                    'id' => $onuKey, 'label' => 'ONU '.$mapping->onu_id, 'group' => 'onu',
                    'status' => $status,
                    'title' => $customerName.' · PON '.($mapping->pon_port ?: '—').' · '.($mapping->onu_serial ?: $mapping->onu_mac ?: 'No serial/MAC').' · Last seen '.$lastSeen,
                    'url' => route('network-inventory.olt.customers', $mapping->olt_device_id),
                ];
                $oltKey = 'device:'.$mapping->olt_device_id;
                if ($existing->has($oltKey)) {
                    $graphEdges[] = ['id'=>'auto:pon-onu:'.$mapping->id,'from'=>$ponKey,'to'=>$onuKey,'label'=>$mapping->pon_port ?: 'ONU','connection_type'=>'splitter','arrows'=>'to','capacity_mbps'=>null,'traffic_mbps'=>null,'utilization'=>null];
                }
                if ($mapping->customer_id && $mapping->customer) {
                    $customerKey = 'customer:'.$mapping->customer_id;
                    if (! $existing->has($customerKey)) {
                        $nodes[] = [
                            'id' => $customerKey, 'label' => $customerName, 'group' => 'customer',
                            'status' => $mapping->customer->status ?: 'unknown',
                            'title' => 'Customer '.$mapping->customer->customer_unique_id,
                            'url' => route('customer.details', encrypt($mapping->customer->customer_unique_id)),
                        ];
                        $existing->put($customerKey, true);
                    }
                    $graphEdges[] = ['id'=>'auto:onu-customer:'.$mapping->id,'from'=>$onuKey,'to'=>$customerKey,'label'=>'service','connection_type'=>'logical_service','arrows'=>'to','dashes'=>true,'capacity_mbps'=>null,'traffic_mbps'=>null,'utilization'=>null];
                }
            }
        }

        $mappingHealth = null;
        if ($this->mode === 'live') {
            $mappingQuery = OltOnuCustomerMapping::query();
            $mappingHealth = [
                'total' => (clone $mappingQuery)->count(),
                'missing_olt' => (clone $mappingQuery)->whereDoesntHave('olt')->count(),
                'unmapped_customer' => (clone $mappingQuery)->whereNull('customer_id')->count(),
                'stale' => (clone $mappingQuery)->whereNotNull('last_seen_at')->where('last_seen_at', '<', now()->subMinutes(15))->count(),
                'missing_identifier' => (clone $mappingQuery)->where(function ($q) { $q->whereNull('onu_id')->orWhere('onu_id', ''); })->count(),
                'missing_identity' => (clone $mappingQuery)->where(function ($q) { $q->whereNull('onu_serial')->whereNull('onu_mac'); })->count(),
                'invalid_status' => (clone $mappingQuery)->whereNotIn('status', ['online', 'offline', 'unknown'])->count(),
            ];
        }

        $nodeOptions = $this->nodeOptions();
        $customNodes = NetworkTopologyNode::query()->when($this->mode === 'live', fn ($q) => $q->where('is_published', true))->latest()->get();
        // Calculate splitter port occupancy from topology links. Each source/target port is treated as a physical port.
        $portStats = [];
        foreach ($customNodes as $splitter) {
            if ($splitter->type !== 'splitter') continue;
            $outCapacity = (int) ($splitter->output_ports ?: $splitter->splitter_ratio ?: 0);
            $inCapacity = (int) ($splitter->input_ports ?: 1);
            $usedOut = $links->filter(fn ($l) => $l->source_key === 'custom:'.$splitter->id && filled($l->source_port))->pluck('source_port')->map(fn($p) => 'OUT-'.preg_replace('/^OUT-|^P/i', '', (string)$p))->unique()->values()->all();
            $usedIn = $links->filter(fn ($l) => $l->target_key === 'custom:'.$splitter->id && filled($l->target_port))->pluck('target_port')->map(fn($p) => 'IN-'.preg_replace('/^IN-|^P/i', '', (string)$p))->unique()->values()->all();
            $portStats[$splitter->id] = ['in_capacity'=>$inCapacity,'out_capacity'=>$outCapacity,'in_used'=>count($usedIn),'out_used'=>count($usedOut),'in_free'=>max(0,$inCapacity-count($usedIn)),'out_free'=>max(0,$outCapacity-count($usedOut)),'in_ports'=>$usedIn,'out_ports'=>$usedOut];
        }
        $statusSummary = collect($nodes)->countBy('status')->all();
        return view('livewire.network-topology', compact('nodes', 'links', 'graphEdges', 'nodeOptions', 'customNodes', 'statusSummary', 'mappingHealth', 'portStats'))
            ->layout('layouts.app');
    }
}
