@push('styles')
<link rel="stylesheet" href="{{ asset('xlink-network-monitoring/network-monitoring-polish.css') }}">
@endpush
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h3 class="mb-1"><i class="bi bi-bell me-2"></i>Logs & Alerts</h3><small class="text-muted">MikroTik login, logout and authentication events</small></div>
        <button class="btn btn-primary" wire:click="sync"><i class="bi bi-arrow-repeat me-1"></i>Queue Sync</button>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small>Login</small><h4>{{ number_format($login) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small>Logout</small><h4>{{ number_format($logout) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small>Auth Failed</small><h4 class="text-danger">{{ number_format($failed) }}</h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small>Total</small><h4>{{ number_format($logs->total()) }}</h4></div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-3"><select class="form-select" wire:model.live="router"><option value="">All Routers</option>@foreach($routers as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach</select></div>
                <div class="col-md-3"><select class="form-select" wire:model.live="event"><option value="">All Events</option><option value="login">Login</option><option value="logout">Logout</option><option value="auth_failed">Auth Failed</option><option value="other">Other</option></select></div>
                <div class="col-md-6"><input class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search message"></div>
            </div>
            <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Router Time</th><th>Router</th><th>Event</th><th>PPPoE User</th><th>Message</th></tr></thead><tbody>
            @forelse($logs as $log)
                @php($type = $log->event_type ?? 'other')
                <tr>
                    <td>{{ optional($log->created_at)->format('d M H:i:s') }}</td><td>{{ $log->router_name }}</td>
                    <td><span class="badge text-bg-{{ $type === 'auth_failed' ? 'danger' : ($type === 'login' ? 'success' : ($type === 'logout' ? 'secondary' : 'light')) }}">{{ strtoupper(str_replace('_', ' ', $type)) }}</span></td>
                    <td>—</td><td>{{ $log->message }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-5">No matching RouterOS log.</td></tr>
            @endforelse
            </tbody></table></div>
            {{ $logs->links() }}
        </div>
    </div>
</div>