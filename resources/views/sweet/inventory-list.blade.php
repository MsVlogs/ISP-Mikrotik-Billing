<div class="container-fluid px-1">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div><h3 class="mb-1"><i class="bi bi-hdd-network me-2"></i>{{ $label }} Inventory</h3><div class="text-muted">Database-backed network device inventory</div></div>
    <a class="btn btn-outline-secondary" href="{{ route('network-inventory') }}">Back to Inventory</a>
  </div>
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between"><strong>{{ $devices->count() }} devices</strong><span class="text-muted">Name · IP · Vendor/Model · Status · Location</span></div>
    <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Name</th><th>IP</th><th>Vendor / Model</th><th>Status</th><th>Location</th></tr></thead><tbody>
      @forelse($devices as $device)
        <tr><td class="fw-semibold">{{ $device->name }}</td><td>{{ $device->ip_address ?: '—' }}</td><td>{{ trim(($device->vendor ?: '').' '.($device->model ?: '')) ?: '—' }}</td><td><span class="badge {{ $device->status === 'online' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($device->status) }}</span></td><td>{{ $device->location ?: '—' }}</td></tr>
      @empty
        <tr><td colspan="5" class="text-center text-muted py-4">No {{ strtolower($label) }} records yet. Add inventory records to populate this table.</td></tr>
      @endforelse
    </tbody></table></div>
  </div>
</div>
