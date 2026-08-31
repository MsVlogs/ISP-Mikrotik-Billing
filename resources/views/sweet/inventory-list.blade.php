<div class="container-fluid px-1">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div><h3 class="mb-1"><i class="bi bi-hdd-network me-2"></i>{{ $label }} Inventory</h3><div class="text-muted">Database-backed device inventory</div></div>
    <a class="btn btn-outline-secondary" href="{{ route('network-inventory') }}">Back to Inventory</a>
  </div>
  @if(session('inventory_message')) <div class="alert alert-success">{{ session('inventory_message') }}</div> @endif
  @if($errors->any()) <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
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
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-5"><label class="form-label">Search</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Name, IP, vendor, model or location"></div>
        <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option><option value="online" @selected(request('status')==='online')>Online</option><option value="offline" @selected(request('status')==='offline')>Offline</option><option value="unknown" @selected(request('status')==='unknown')>Unknown</option></select></div>
        <div class="col-md-2"><label class="form-label">Per page</label><select name="per_page" class="form-select"><option value="10" @selected((int)request('per_page',10)===10)>10</option><option value="25" @selected((int)request('per_page',10)===25)>25</option><option value="50" @selected((int)request('per_page',10)===50)>50</option></select></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary">Filter</button><a href="{{ url()->current() }}" class="btn btn-outline-secondary">Reset</a></div>
      </form>
    </div>
  </div>
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between"><strong>{{ $devices->total() }} devices</strong><span class="text-muted">Search, edit and manage inventory</span></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Name</th><th>IP</th><th>Vendor / Model</th><th>Status</th><th>Location</th><th>Action</th></tr></thead><tbody>
      @forelse($devices as $device)
        <tr>
          <td><form method="POST" action="{{ route('network-inventory.devices.update', [$type, $device->id]) }}" class="row g-2"><div class="col-12"><input name="name" value="{{ $device->name }}" class="form-control form-control-sm"></div></td>
          <td><input name="ip_address" value="{{ $device->ip_address }}" class="form-control form-control-sm"></td>
          <td><div class="d-flex gap-1"><input name="vendor" value="{{ $device->vendor }}" class="form-control form-control-sm" placeholder="Vendor"><input name="model" value="{{ $device->model }}" class="form-control form-control-sm" placeholder="Model"></div></td>
          <td><select name="status" class="form-select form-select-sm"><option value="online" @selected($device->status==='online')>Online</option><option value="offline" @selected($device->status==='offline')>Offline</option><option value="unknown" @selected($device->status==='unknown')>Unknown</option></select></td>
          <td><input name="location" value="{{ $device->location }}" class="form-control form-control-sm"></td>
          <td class="text-nowrap">@csrf @method('PUT')<input type="hidden" name="notes" value="{{ $device->notes }}"><button class="btn btn-sm btn-outline-primary">Save</button></form>
            <form method="POST" action="{{ route('network-inventory.devices.destroy', [$type, $device->id]) }}" class="d-inline" onsubmit="return confirm('Delete this inventory record?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center text-muted py-4">No {{ strtolower($label) }} records match the current filter.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="card-footer">{{ $devices->links() }}</div>
  </div>
</div>
