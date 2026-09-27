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
        ]);

        NetworkTopologyLink::updateOrCreate(
            ['source_key' => $data['source_key'], 'target_key' => $data['target_key'], 'connection_type' => $data['connection_type']],
            ['label' => $data['label'] ?: null, 'status' => 'unknown', 'is_published' => false, 'created_by' => auth()->id()]
        );

        $this->reset(['source_key', 'target_key', 'label']);
        $this->connection_type = 'fiber_core';
        $this->message = 'Topology connection saved as a draft. Publish it to show it in Live Topology.';
    }

    public function saveNode(): void
    {
        if ($this->mode !== 'designer') abort(403);
        $data = $this->validate([
            'newNodeType' => ['required', Rule::in(['splitter', 'odf', 'rack', 'pop', 'fiber_segment'])],
            'newNodeName' => ['required', 'string', 'max:120'],
            'newNodeLocation' => ['nullable', 'string', 'max:160'],
            'newNodeNotes' => ['nullable', 'string', 'max:1000'],
        ]);
        NetworkTopologyNode::create([
            'type' => $data['newNodeType'], 'name' => trim($data['newNodeName']),
            'location' => $data['newNodeLocation'] ?: null, 'notes' => $data['newNodeNotes'] ?: null,
            'is_published' => false, 'created_by' => auth()->id(),
        ]);
        $this->reset(['newNodeName', 'newNodeLocation', 'newNodeNotes']);
        $this->newNodeType = 'splitter';
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
                'label' => $link->label ?: str_replace('_', ' ', $link->connection_type),
                'connection_type' => $link->connection_type, 'arrows' => 'to',
                'dashes' => $link->connection_type === 'logical_service',
            ];
        }

        if ($this->mode === 'live') {
            $mappings = OltOnuCustomerMapping::with(['customer', 'olt'])->latest('last_seen_at')->limit(500)->get();
            $existing = collect($nodes)->keyBy('id');
            foreach ($mappings as $mapping) {
                if (! $mapping->olt || ! $mapping->onu_id) continue;
                $onuKey = 'onu:'.$mapping->id;
                $customerName = $mapping->customer?->customer_name ?: $mapping->customer?->customer_unique_id ?: 'Unmapped ONU';
                $nodes[] = [
                    'id' => $onuKey, 'label' => 'ONU '.$mapping->onu_id, 'group' => 'onu',
                    'status' => $mapping->status ?: 'unknown',
                    'title' => $customerName.' · PON '.($mapping->pon_port ?: '—').' · '.($mapping->onu_serial ?: $mapping->onu_mac ?: 'No serial/MAC'),
                    'url' => route('network-inventory.olt.customers', $mapping->olt_device_id),
                ];
                $oltKey = 'device:'.$mapping->olt_device_id;
                if ($existing->has($oltKey)) {
                    $graphEdges[] = ['id'=>'auto:olt-onu:'.$mapping->id,'from'=>$oltKey,'to'=>$onuKey,'label'=>$mapping->pon_port ?: 'PON','connection_type'=>'fiber_core','arrows'=>'to'];
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
                    $graphEdges[] = ['id'=>'auto:onu-customer:'.$mapping->id,'from'=>$onuKey,'to'=>$customerKey,'label'=>'service','connection_type'=>'logical_service','arrows'=>'to','dashes'=>true];
                }
            }
        }

        $nodeOptions = $this->nodeOptions();
        $customNodes = NetworkTopologyNode::query()->when($this->mode === 'live', fn ($q) => $q->where('is_published', true))->latest()->get();
        return view('livewire.network-topology', compact('nodes', 'links', 'graphEdges', 'nodeOptions', 'customNodes'))
            ->layout('layouts.app');
    }
}
