<div class="container-fluid px-1">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div><h3 class="mb-1"><i class="bi bi-hdd-network me-2"></i>{{ $label }} Inventory</h3><div class="text-muted">Database-backed device inventory</div></div>
    <a class="btn btn-outline-secondary" href="{{ route('network-inventory') }}">Back to Inventory</a>
  </div>
  @if(session('inventory_message')) <div class="alert alert-success">{{ session('inventory_message') }}</div> @endif
  <div class="card shadow-sm mb-4">
    <div class="card-header"><strong>Add {{ $label }}</strong></div>
    <div class="card-body">
      <form method="POST" action="{{ route('network-inventory.devices.store', $type) }}" class="row g-3">
        @csrf
        <div class="col-md-3"><label class="form-label">Name</label><input required name="name" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">IP</label><input name="ip_address" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Vendor</label><input name="vendor" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Model</label><input name="model" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Location</label><input name="location" class="form-control"></div>
        <div class="col-md-1"><label class="form-label">Status</label><select name="status" class="form-select"><option>online</option><option>offline</option><option>unknown</option></select></div>
        <div class="col-12"><label class="form-label">Notes</label><input name="notes" class="form-control"></div>
        <div class="col-12"><button class="btn btn-primary">Add Device</button></div>
      </form>
    </div>
  </div>
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between"><strong>{{ $devices->count() }} devices</strong><span class="text-muted">Search and manage inventory</span></div>
    <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Name</th><th>IP</th><th>Vendor / Model</th><th>Status</th><th>Location</th><th>Action</th></tr></thead><tbody>
      @forelse($devices as $device)
        <tr><td class="fw-semibold">{{ $device->name }}</td><td>{{ $device->ip_address ?: '—' }}</td><td>{{ trim(($device->vendor ?: '').' '.($device->model ?: '')) ?: '—' }}</td><td><span class="badge {{ $device->status === 'online' ? 'bg-success' : ($device->status === 'offline' ? 'bg-danger' : 'bg-secondary') }}">{{ ucfirst($device->status) }}</span></td><td>{{ $device->location ?: '—' }}</td>
          <td><form method="POST" action="{{ route('network-inventory.devices.destroy', [$type, $device->id]) }}" onsubmit="return confirm('Delete this inventory record?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
      @empty
        <tr><td colspan="6" class="text-center text-muted py-4">No {{ strtolower($label) }} records yet.</td></tr>
      @endforelse
    </tbody></table></div>
  </div>
</div>
