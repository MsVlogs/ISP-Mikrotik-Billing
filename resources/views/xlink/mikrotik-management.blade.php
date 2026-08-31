<x-app-layout>
<div class="container-fluid px-1">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div><h3 class="mb-1"><i class="bi bi-router me-2"></i>MikroTik Management</h3><div class="text-muted">Router inventory, connection state and management shortcuts</div></div>
    <span class="badge bg-success-subtle text-success">{{ $online }} / {{ $routers->count() }} Online</span>
  </div>
  <div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Total Routers</small><div class="fs-3 fw-bold mt-2">{{ $routers->count() }}</div></div></div></div>
    <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Online</small><div class="fs-3 fw-bold mt-2 text-success">{{ $online }}</div></div></div></div>
    <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Offline</small><div class="fs-3 fw-bold mt-2 text-danger">{{ max(0,$routers->count()-$online) }}</div></div></div></div>
  </div>
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Router Inventory</h5><a class="btn btn-sm btn-primary" href="{{ route('mikrotik-server') }}" wire:navigate.hover>Full MikroTik Console</a></div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Router</th><th>IP Address</th><th>API Port</th><th>Status</th><th class="text-end">Action</th></tr></thead>
        <tbody>
          @forelse($routers as $router)
            <tr>
              <td class="fw-semibold">{{ $router->router_name }}</td>
              <td>{{ $router->ip_address ?: '—' }}</td>
              <td>{{ $router->api_port ?: '—' }}</td>
              <td><span class="badge {{ $router->action === 'connected' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($router->action ?: 'unknown') }}</span></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('mikrotik-server') }}" wire:navigate.hover>Manage</a></td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-5">No MikroTik routers found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
</x-app-layout>
