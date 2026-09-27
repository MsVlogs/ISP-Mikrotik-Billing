<div class="container-fluid px-1 pb-4">
<style>
.ne-card{background:#fff;border:1px solid #e7edf3;border-radius:14px;box-shadow:0 5px 22px rgba(31,41,55,.06)}
.ne-kpi{padding:18px}.ne-kpi .n{font-size:28px;font-weight:800}.ne-muted{color:#64748b;font-size:.84rem}
.ne-table th,.ne-table td{padding:11px 12px;border-bottom:1px solid #edf1f5;font-size:.84rem;vertical-align:middle}.ne-table{width:100%}
.ne-dot{width:9px;height:9px;border-radius:50%;display:inline-block;margin-right:7px}.critical{background:#dc3545}.warning{background:#f59e0b}.info{background:#0d6efd}
</style>
<div class="d-flex justify-content-between align-items-center mb-3">
 <div><span class="text-uppercase small text-muted fw-semibold">Network Centre</span><h3 class="mb-0">Events & Alerts</h3><small class="ne-muted">Centralized device incidents with acknowledge and resolve workflow</small></div>
 <a href="{{ route('device-manager') }}" class="btn btn-outline-secondary"><i class="bi bi-router me-1"></i> Device Manager</a>
</div>
@if($message)<div class="alert alert-success py-2">{{ $message }}</div>@endif
<div class="row g-3 mb-3">
 <div class="col-6 col-xl-3"><div class="ne-card ne-kpi"><span class="ne-muted">Open alerts</span><div class="n text-danger">{{ $open }}</div></div></div>
 <div class="col-6 col-xl-3"><div class="ne-card ne-kpi"><span class="ne-muted">Critical</span><div class="n text-danger">{{ $critical }}</div></div></div>
 <div class="col-6 col-xl-3"><div class="ne-card ne-kpi"><span class="ne-muted">Warning</span><div class="n text-warning">{{ $warning }}</div></div></div>
 <div class="col-6 col-xl-3"><div class="ne-card ne-kpi"><span class="ne-muted">Resolved today</span><div class="n text-success">{{ $resolvedToday }}</div></div></div>
</div>
<div class="ne-card mb-3"><div class="p-3"><div class="row g-2">
 <div class="col-lg-6"><input wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search alert, device, IP or event type"></div>
 <div class="col-lg-3"><select wire:model.live="severity" class="form-select"><option value="">All severities</option><option value="critical">Critical</option><option value="warning">Warning</option><option value="info">Info</option></select></div>
 <div class="col-lg-3"><select wire:model.live="status" class="form-select"><option value="open">Open</option><option value="acknowledged">Acknowledged</option><option value="resolved">Resolved</option><option value="all">All statuses</option></select></div>
 </div></div></div>
<div class="ne-card"><div class="table-responsive"><table class="table ne-table mb-0"><thead><tr><th>Severity</th><th>Alert</th><th>Device</th><th>Occurrences</th><th>Last seen</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
@forelse($events as $event)
<tr>
 <td><span class="ne-dot {{ $event->severity }}"></span>{{ ucfirst($event->severity) }}</td>
 <td><strong>{{ $event->title }}</strong><div class="ne-muted">{{ $event->event_type }} · {{ Str::limit($event->message, 100) }}</div></td>
 <td>
 @if($event->device)
  <a href="{{ route('device-manager.detail', ['kind' => $event->device->type === 'olt' ? 'olt' : 'device', 'device' => $event->device->id]) }}">{{ $event->device->name }}</a><div class="ne-muted">{{ $event->device->ip_address ?: '—' }}</div>
 @else
  @php($router = $routers[(int) data_get($event->metadata, 'router_id')] ?? null)
  @if($router)<a href="{{ route('device-manager.detail', ['kind' => 'mikrotik', 'device' => $router->id]) }}">{{ $router->router_name }}</a><div class="ne-muted">{{ $event->title }}</div>@else{{ 'Network device' }}@endif
 @endif
 </td>
 <td>{{ $event->occurrences }}</td><td>{{ optional($event->last_seen_at)->format('d M Y H:i:s') ?: optional($event->created_at)->format('d M Y H:i:s') }}</td>
 <td><span class="badge {{ $event->status==='resolved'?'bg-success':($event->status==='acknowledged'?'bg-warning text-dark':'bg-danger') }}">{{ ucfirst($event->status) }}</span>
 @if($event->status==='acknowledged' && $event->acknowledgedBy)<div class="ne-muted">ACK: {{ $event->acknowledgedBy->name }} · {{ optional($event->acknowledged_at)->diffForHumans() }}</div>@endif
 @if($event->status==='resolved')<div class="ne-muted">Resolved: {{ $event->resolvedBy?->name ?: 'Monitor' }} · {{ optional($event->resolved_at)->diffForHumans() ?: '—' }}</div>@endif
 </td>
 <td class="text-end"><div class="btn-group btn-group-sm">
 @if($event->status==='open')<button wire:click="acknowledge({{ $event->id }})" class="btn btn-outline-warning">ACK</button>@endif
 @if($event->status!=='resolved')<button wire:click="resolve({{ $event->id }})" class="btn btn-outline-success">Resolve</button>@else<button wire:click="reopen({{ $event->id }})" class="btn btn-outline-secondary">Reopen</button>@endif
 </div></td>
</tr>
@empty<tr><td colspan="7" class="text-center py-5 text-muted">No alerts match the selected filters.</td></tr>@endforelse
</tbody></table></div><div class="p-3">{{ $events->links() }}</div></div>
</div>
