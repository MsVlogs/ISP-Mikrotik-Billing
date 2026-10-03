<div class="container-fluid px-1 pb-4">
<style>
.nt-card{background:#fff;border:1px solid #e6ebf2;border-radius:14px;box-shadow:0 5px 20px rgba(31,41,55,.05)}
.nt-muted{color:#64748b;font-size:.84rem}.nt-canvas{height:650px;border-radius:12px;border:1px solid #e2e8f0;background:radial-gradient(circle at 1px 1px,#dbe3ee 1px,transparent 0);background-size:20px 20px}
.nt-table th,.nt-table td{padding:11px 12px;border-bottom:1px solid #edf1f5;vertical-align:middle;font-size:.86rem}
</style>
<link rel="stylesheet" href="https://unpkg.com/vis-network/styles/vis-network.min.css">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
 <div><span class="text-uppercase small text-muted fw-semibold">Network Centre</span><h3 class="mb-0">{{ $mode==='designer'?'Topology Designer':'Live Topology' }}</h3><small class="nt-muted">Device links, uplinks, fiber cores and logical service paths. Live view includes saved OLT/ONU/customer relationships.</small></div>
 <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('device-manager') }}"><i class="bi bi-hdd-network me-1"></i>Device Manager</a><a class="btn btn-outline-primary" href="{{ route('network-map') }}"><i class="bi bi-map me-1"></i>Geographic Map</a><a class="btn btn-outline-info" href="{{ route('optical-audit') }}"><i class="bi bi-activity me-1"></i>Optical Audit</a>
 @if($mode==='designer')<a class="btn btn-success" href="{{ route('network-topology.live') }}"><i class="bi bi-broadcast me-1"></i>Open Live Topology</a>@else<a class="btn btn-outline-primary" href="{{ route('network-topology.designer') }}"><i class="bi bi-pencil-square me-1"></i>Topology Designer</a>@endif</div>
</div>
@if($message)<div class="alert alert-success py-2">{{ $message }}</div>@endif
@if($mode==='live')<div class="alert alert-info py-2"><strong>Read-only live view.</strong> Device availability comes from the latest saved snapshot. ONU/customer links come from the current OLT mapping ledger; no demo devices are inserted.</div>@endif
@if($mode==='live' && $mappingHealth)<div class="row g-2 mb-3"><div class="col-md-3"><div class="nt-card p-3"><div class="nt-muted">ONU mappings</div><strong class="fs-5">{{ $mappingHealth['total'] }}</strong></div></div><div class="col-md-3"><div class="nt-card p-3"><div class="nt-muted">Unmapped customer</div><strong class="fs-5">{{ $mappingHealth['unmapped_customer'] }}</strong></div></div><div class="col-md-3"><div class="nt-card p-3"><div class="nt-muted">Stale &gt; 15 min</div><strong class="fs-5">{{ $mappingHealth['stale'] }}</strong></div></div><div class="col-md-3"><div class="nt-card p-3"><div class="nt-muted">Data issues</div><strong class="fs-5">{{ $mappingHealth['missing_olt'] + $mappingHealth['missing_identifier'] + $mappingHealth['missing_identity'] + $mappingHealth['invalid_status'] }}</strong></div></div></div>@endif
<div class="row g-3">
 <div class="{{ $mode==='designer'?'col-xl-8':'col-12' }}"><div class="nt-card p-3">
  <div class="d-flex justify-content-between align-items-center mb-2"><div><strong>Network Graph</strong><div class="nt-muted">{{ count($nodes) }} nodes · {{ count($graphEdges) }} connections</div></div><span class="badge bg-light text-dark">Click a node to open its workspace</span></div>
  <div class="d-flex flex-wrap gap-2 mb-2" id="xlink-topology-filters"><button type="button" class="btn btn-sm btn-outline-dark active" data-status-filter="all">All</button><button type="button" class="btn btn-sm btn-outline-success" data-status-filter="online">Online</button><button type="button" class="btn btn-sm btn-outline-danger" data-status-filter="offline">Offline</button><button type="button" class="btn btn-sm btn-outline-secondary" data-status-filter="unknown">Unknown</button></div>
  <div id="xlink-topology-graph" class="nt-canvas"></div>
  <div class="d-flex flex-wrap gap-2 mt-2"><span class="badge bg-success">Online {{ $statusSummary['online'] ?? 0 }}</span><span class="badge bg-danger">Offline {{ $statusSummary['offline'] ?? 0 }}</span><span class="badge bg-secondary">Unknown {{ $statusSummary['unknown'] ?? 0 }}</span><span class="badge bg-light text-dark border">{{ count($graphEdges) }} paths</span></div><div class="nt-muted mt-2">Green = online · Red = offline · Grey = unknown · Dashed lines = logical service links. Use the status filters below to isolate affected paths.</div>
 </div></div>
 @if($mode==='designer')
 <div class="col-xl-4"><div class="nt-card p-3"><h5 class="mb-1">Create Connection</h5><p class="nt-muted">Select existing inventory nodes. New connections remain drafts until published.</p>
  <form wire:submit.prevent="saveLink" class="d-grid gap-3">
   <div><label class="form-label">Source device</label><select wire:model="source_key" class="form-select" required><option value="">Choose source...</option>@foreach($nodeOptions as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>@error('source_key')<small class="text-danger">{{ $message }}</small>@enderror</div>
   <div><label class="form-label">Target device</label><select wire:model="target_key" class="form-select" required><option value="">Choose target...</option>@foreach($nodeOptions as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>@error('target_key')<small class="text-danger">{{ $message }}</small>@enderror</div>
   <div><label class="form-label">Connection type</label><select wire:model="connection_type" class="form-select"><option value="uplink">Uplink</option><option value="ethernet">Ethernet</option><option value="fiber_core">Fiber core</option><option value="splitter">Splitter</option><option value="logical_service">Logical service</option></select></div>
   <div><label class="form-label">Label (optional)</label><input wire:model="label" class="form-control" placeholder="e.g. Core uplink / Fiber 01">
   <div class="row g-2 mt-1"><div class="col-6"><input wire:model="capacity_mbps" type="number" min="1" class="form-control" placeholder="Capacity Mbps"></div><div class="col-6"><input wire:model="traffic_mbps" type="number" min="0" class="form-control" placeholder="Traffic Mbps"></div><div class="col-6"><input wire:model="latency_ms" type="number" step="0.01" min="0" class="form-control" placeholder="Latency ms"></div><div class="col-6"><input wire:model="packet_loss" type="number" step="0.01" min="0" max="100" class="form-control" placeholder="Loss %"></div><div class="col-6"><input wire:model="fiber_core" type="number" min="1" class="form-control" placeholder="Fiber core #"></div><div class="col-6"><select wire:model="fiber_type" class="form-select"><option value="singlemode">Single-mode</option><option value="multimode">Multi-mode</option><option value="drop">Drop fiber</option><option value="unknown">Unknown</option></select></div></div>@error('label')<small class="text-danger">{{ $message }}</small>@enderror</div>
   <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Save draft connection</button>
  </form>
 </div></div>
 <div class="col-xl-4"><div class="nt-card p-3"><h5 class="mb-1">Add Network Node</h5><p class="nt-muted">Add logical infrastructure such as a splitter, ODF, rack, POP or fiber segment.</p>
  <form wire:submit.prevent="saveNode" class="d-grid gap-3">
   <div><label class="form-label">Node type</label><select wire:model="newNodeType" class="form-select"><option value="splitter">Fiber Splitter</option><option value="olt_pon">OLT PON Port</option><option value="odf">ODF</option><option value="rack">Rack</option><option value="pop">POP</option><option value="fiber_segment">Fiber segment</option></select></div>
   <div><label class="form-label">Name / reference *</label><input wire:model="newNodeName" class="form-control" required placeholder="e.g. SPL-POP1-01">@error('newNodeName')<small class="text-danger">{{ $message }}</small>@enderror</div>
   <div><label class="form-label">POP / Location</label><input wire:model="newNodeLocation" class="form-control" placeholder="e.g. Mirpur POP"></div>
   <div class="row g-2"><div class="col-6"><input wire:model="port_reference" class="form-control" placeholder="PON / port ref"></div><div class="col-6"><input wire:model="splitter_ratio" type="number" min="2" max="64" class="form-control" placeholder="Splitter 1:8 etc."></div></div>
   <div><label class="form-label">Notes</label><textarea wire:model="newNodeNotes" class="form-control" rows="2"></textarea></div>
   <button class="btn btn-outline-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Save node as draft</button>
  </form>
 </div></div>
 <div class="col-xl-8"><div class="nt-card"><div class="p-3 border-bottom d-flex justify-content-between"><strong>Custom Node Registry</strong><span class="nt-muted">{{ $customNodes->count() }} nodes</span></div><div class="table-responsive"><table class="table nt-table mb-0"><thead><tr><th>Type</th><th>Name</th><th>Location</th><th>Visibility</th><th class="text-end">Actions</th></tr></thead><tbody>
 @forelse($customNodes as $node)<tr><td>{{ strtoupper(str_replace('_',' ',$node->type)) }}</td><td>{{ $node->name }}</td><td>{{ $node->location ?: '—' }}</td><td><span class="badge {{ $node->is_published?'bg-success':'bg-secondary' }}">{{ $node->is_published?'Published':'Draft' }}</span></td><td class="text-end"><div class="btn-group btn-group-sm">@if($node->is_published)<button wire:click="unpublishNode({{ $node->id }})" class="btn btn-outline-secondary">Unpublish</button>@else<button wire:click="publishNode({{ $node->id }})" class="btn btn-outline-success">Publish</button>@endif<button wire:click="deleteNode({{ $node->id }})" wire:confirm="Delete this node and its connections?" class="btn btn-outline-danger">Delete</button></div></td></tr>
 @empty<tr><td colspan="5" class="text-center py-4 text-muted">No custom topology nodes yet.</td></tr>@endforelse
 </tbody></table></div></div></div>
 <div class="col-12"><div class="nt-card"><div class="p-3 border-bottom d-flex justify-content-between"><strong>Connection Registry</strong><span class="nt-muted">{{ $links->count() }} saved connections</span></div><div class="table-responsive"><table class="table nt-table mb-0"><thead><tr><th>Source</th><th>Connection</th><th>Target</th><th>Status</th><th>Visibility</th><th class="text-end">Actions</th></tr></thead><tbody>
 @forelse($links as $link)<tr><td>{{ $nodeOptions[$link->source_key] ?? $link->source_key }}</td><td>{{ $link->label ?: str_replace('_',' ',$link->connection_type) }}</td><td>{{ $nodeOptions[$link->target_key] ?? $link->target_key }}</td><td>{{ ucfirst($link->status) }}</td><td><span class="badge {{ $link->is_published?'bg-success':'bg-secondary' }}">{{ $link->is_published?'Published':'Draft' }}</span></td><td class="text-end"><div class="btn-group btn-group-sm">@if($link->is_published)<button wire:click="unpublish({{ $link->id }})" class="btn btn-outline-secondary">Unpublish</button>@else<button wire:click="publish({{ $link->id }})" class="btn btn-outline-success">Publish</button>@endif<button wire:click="deleteLink({{ $link->id }})" wire:confirm="Delete this topology connection?" class="btn btn-outline-danger">Delete</button></div></td></tr>
 @empty<tr><td colspan="6" class="text-center py-4 text-muted">No saved links yet. Create a connection to begin.</td></tr>@endforelse
 </tbody></table></div></div></div>
 @endif
</div>
<script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
<script>
window.initXlinkTopology = function () {
 const el = document.getElementById('xlink-topology-graph');
 if (!el || !window.vis) return;
 if (el._network) { el._network.destroy(); el._network = null; }
 const rawNodes = @json($nodes);
 const rawEdges = @json($graphEdges);
 const nodes = new vis.DataSet(rawNodes.map(n => ({
  ...n, color: n.status === 'online' ? '#22c55e' : (n.status === 'offline' ? '#ef4444' : (n.group === 'splitter' ? '#f59e0b' : '#94a3b8')),
  shape: n.group === 'router' ? 'box' : (n.group === 'olt' ? 'database' : (n.group === 'onu' ? 'diamond' : (n.group === 'customer' ? 'ellipse' : (n.group === 'splitter' ? 'triangle' : (n.group === 'odf' ? 'hexagon' : (n.group === 'pop' ? 'star' : (n.group === 'fiber_segment' ? 'text' : 'box'))))))),
  font: {color:'#172033', size:13}, borderWidth:1, margin:12
 })));
 const edgeRows = rawEdges.map(e => ({...e, color:{color:e.connection_type==='fiber_core' || e.connection_type==='splitter'?'#16a34a':(e.connection_type==='uplink'?'#2563eb':'#94a3b8')}, font:{size:10,align:'middle'}, smooth:{type:'dynamic'}}));
 const edges = new vis.DataSet(edgeRows);
 const network = new vis.Network(el, {nodes, edges}, {
  autoResize:true, interaction:{hover:true,navigationButtons:true,keyboard:{enabled:true}},
  physics:{enabled:true, stabilization:{iterations:180}, barnesHut:{gravitationalConstant:-4500,springLength:150,springConstant:0.035}},
  edges:{arrows:{to:{enabled:true,scaleFactor:0.65}},width:2},
  groups:{router:{shape:'box'},olt:{shape:'database'},onu:{shape:'diamond'},customer:{shape:'ellipse'},splitter:{shape:'triangle'},olt_pon:{shape:'dot'},odf:{shape:'hexagon'},rack:{shape:'box'},pop:{shape:'star'},fiber_segment:{shape:'text'}}
 });
 el._network = network;
 const filterRoot = document.getElementById('xlink-topology-filters');
 filterRoot?.querySelectorAll('[data-status-filter]').forEach(button => button.addEventListener('click', () => {
  const wanted = button.dataset.statusFilter || 'all';
  filterRoot.querySelectorAll('[data-status-filter]').forEach(b => b.classList.toggle('active', b === button));
  const visible = new Set(rawNodes.filter(n => wanted === 'all' || n.status === wanted).map(n => n.id));
  nodes.update(rawNodes.map(n => ({id:n.id, hidden: !visible.has(n.id)})));
  edges.update(edgeRows.map(e => ({id:e.id, hidden: !(visible.has(e.from) && visible.has(e.to))})));
 }));
 network.on('click', function(params) {
  if (!params.nodes.length) return;
  const node = nodes.get(params.nodes[0]);
  if (node && node.url) window.location.href = node.url;
 });
};
document.addEventListener('livewire:navigated', () => window.initXlinkTopology?.());
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => window.initXlinkTopology?.()); else window.initXlinkTopology?.();
</script>
</div>
