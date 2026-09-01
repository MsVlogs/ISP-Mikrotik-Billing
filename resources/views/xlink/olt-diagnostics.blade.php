<x-app-layout>
@push('styles')<link rel="stylesheet" href="{{ asset('xlink-olt/olt-source.css') }}">@endpush
<div class="container-fluid px-1">
<div class="d-flex justify-content-between align-items-center mb-3"><div><span class="text-uppercase small text-muted fw-semibold">Admin Panel</span><h3 class="mb-0">OLT Diagnostics</h3><small class="text-muted">Health and configuration diagnostics for {{ $device->name }}</small></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('network-inventory.olt.edit',$device) }}">Edit OLT</a><a class="btn btn-outline-secondary" href="{{ route('network-inventory.olt') }}">Back</a></div></div>
@if(session('diagnostic_message'))<div class="alert alert-success">{{ session('diagnostic_message') }}</div>@endif
<div class="row g-3">
<div class="col-lg-4"><div class="card h-100"><div class="card-header"><strong>Connection</strong></div><div class="card-body">
<p class="mb-2"><span class="text-muted">Host:</span> <strong>{{ $device->host ?: $device->ip_address ?: '—' }}</strong></p>
<p class="mb-2"><span class="text-muted">Port:</span> <strong>{{ $device->port ?: $device->health_port ?: '—' }}</strong></p>
<p class="mb-2"><span class="text-muted">Web:</span> <strong>{{ $device->web_protocol ?: '—' }}{{ $device->web_port ? ':'.$device->web_port : '' }}</strong></p>
<p class="mb-3"><span class="text-muted">Status:</span> <span class="badge {{ $device->status==='online'?'bg-success':($device->status==='offline'?'bg-danger':'bg-warning text-dark') }}">{{ ucfirst($device->status ?: 'unknown') }}</span></p>
<form method="POST" action="{{ route('network-inventory.olt.diagnostic-check',$device) }}">@csrf<button class="btn btn-primary w-100">Run Connectivity Check</button></form>
</div></div></div>
<div class="col-lg-4"><div class="card h-100"><div class="card-header"><strong>Hardware</strong></div><div class="card-body">
<div class="d-flex justify-content-between"><span>PON Ports</span><strong>{{ $device->pon_ports ?? '—' }}</strong></div><div class="d-flex justify-content-between"><span>GE Ports</span><strong>{{ $device->ge_ports ?? '—' }}</strong></div><div class="d-flex justify-content-between"><span>SFP Ports</span><strong>{{ $device->sfp_ports ?? '—' }}</strong></div><div class="d-flex justify-content-between"><span>SFP+ Ports</span><strong>{{ $device->sfp_plus_ports ?? '—' }}</strong></div><div class="d-flex justify-content-between"><span>Firmware</span><strong>{{ $device->firmware ?: '—' }}</strong></div><div class="d-flex justify-content-between"><span>Serial</span><strong>{{ $device->serial_no ?: '—' }}</strong></div>
</div></div></div>
<div class="col-lg-4"><div class="card h-100"><div class="card-header"><strong>Monitoring</strong></div><div class="card-body">
<div class="d-flex justify-content-between"><span>Monitor</span><strong>{{ ($device->monitor_enabled ?? false) ? 'Enabled' : 'Disabled' }}</strong></div><div class="d-flex justify-content-between"><span>Health Port</span><strong>{{ $device->health_port ?: '—' }}</strong></div><div class="d-flex justify-content-between"><span>Last Latency</span><strong>{{ $device->last_latency_ms !== null ? $device->last_latency_ms.' ms' : '—' }}</strong></div><div class="d-flex justify-content-between"><span>Last Check</span><strong>{{ optional($device->last_checked_at)->format('d M Y H:i:s') ?: 'Never' }}</strong></div><div class="mt-3"><span class="text-muted d-block">Diagnostic Command</span><code>{{ $device->diagnostic_command ?: 'Not configured' }}</code></div>
</div></div></div>
</div></div>
</x-app-layout>
