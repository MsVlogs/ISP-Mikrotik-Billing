<div class="container-fluid px-1">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="mb-1"><i class="bi bi-activity me-2"></i>Network Health History</h3><div class="text-muted">Historical uptime, incidents and latency</div></div>
    <a class="btn btn-outline-secondary" href="{{ route('network-inventory') }}">Back to Inventory</a>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">24h Uptime</small><div class="fs-3 fw-bold">{{ $uptime24 !== null ? $uptime24.'%' : '—' }}</div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">7d Uptime</small><div class="fs-3 fw-bold">{{ $uptime7 !== null ? $uptime7.'%' : '—' }}</div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">7d Incidents</small><div class="fs-3 fw-bold {{ $incidentCount ? 'text-danger' : 'text-success' }}">{{ $incidentCount }}</div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">24h Avg Latency</small><div class="fs-3 fw-bold">{{ $avgLatency !== null ? $avgLatency.' ms' : '—' }}</div></div></div></div>
  </div>
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between"><strong>Recent health checks</strong><span class="text-muted">Latest 100 records</span></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Device</th><th>Type</th><th>Status</th><th>Latency</th><th>Checked</th><th>Details</th></tr></thead><tbody>
      @forelse($checks as $check)
        <tr><td class="fw-semibold">{{ $check->device->name }}</td><td>{{ strtoupper(str_replace('-', ' ', $check->device->type)) }}</td><td><span class="badge {{ $check->status==='online'?'bg-success':($check->status==='degraded'?'bg-warning text-dark':'bg-danger') }}">{{ ucfirst($check->status) }}</span></td><td>{{ $check->latency_ms !== null ? $check->latency_ms.' ms' : '—' }}</td><td>{{ optional($check->checked_at)->format('d M Y H:i:s') }}</td></tr>
      @empty
        <tr><td colspan="5" class="text-center text-muted py-5">No health history yet.</td></tr>
      @endforelse
    </tbody></table></div>
  </div>
</div>
