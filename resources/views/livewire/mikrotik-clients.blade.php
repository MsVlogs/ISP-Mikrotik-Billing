<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="bi bi-list-columns-reverse me-2"></i>{{ __('Mikrotik Clients') }} <small class="text-muted fs-6">{{ __('Export Mikrotik Clients') }}</small></h3>
        <div class="d-flex gap-2 flex-wrap">
            <button wire:click="exportExcel" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('Generate Excel') }}</button>
            <button wire:click="exportClientList" class="btn btn-info btn-sm text-white"><i class="bi bi-person-lines-fill me-1"></i>{{ __('Export To Client List') }}</button>
            <button wire:click="exportMacReseller" class="btn btn-dark btn-sm"><i class="bi bi-credit-card me-1"></i>{{ __('Export To MACReseller') }}</button>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-xl-3 col-md-6"><label class="form-label small fw-bold">{{ __('SERVERS') }}</label><select wire:model.live="router" class="form-select"><option value="">{{ __('All Servers') }}</option>@foreach($routers as $item)<option value="{{ $item->router_name }}">{{ $item->router_name }}</option>@endforeach</select></div>
                <div class="col-xl-3 col-md-6"><label class="form-label small fw-bold">{{ __('PROTOCOL') }}</label><select wire:model.live="protocol" class="form-select"><option value="PPPOE">PPPOE</option><option value="HOTSPOT">HOTSPOT</option><option value="">{{ __('All') }}</option></select></div>
                <div class="col-xl-3 col-md-6"><label class="form-label small fw-bold">{{ __('PROFILE') }}</label><select wire:model.live="profile" class="form-select"><option value="">{{ __('Select') }}</option>@foreach($profiles as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach</select></div>
                <div class="col-xl-3 col-md-6"><label class="form-label small fw-bold">{{ __('USER TYPE') }}</label><select wire:model.live="userType" class="form-select"><option value="Unique">Unique</option><option value="All">All</option></select></div>
                <div class="col-md-8"><label class="form-label small fw-bold">{{ __('SEARCH') }}</label><input wire:model.live.debounce.300ms="search" class="form-control" placeholder="{{ __('Search username, caller ID or server') }}"></div>
                <div class="col-md-4 d-flex justify-content-end gap-2"><button wire:click="clearFilters" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>{{ __('Clear Filter') }}</button><button wire:click="applyFilters" class="btn btn-info text-white"><i class="bi bi-funnel me-1"></i>{{ __('Apply Filter') }}</button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center"><div class="small text-muted">{{ __('Show') }} <select wire:model.live="perPage" class="form-select d-inline-block w-auto form-select-sm"><option>10</option><option>25</option><option>50</option><option>100</option></select> {{ __('entries') }}</div><div class="small text-muted">{{ $clients->total() }} {{ __('clients') }}</div></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark"><tr><th>{{ __('Name') }}</th><th>{{ __('Password') }}</th><th>{{ __('Service') }}</th><th>{{ __('Profile') }}</th><th>{{ __('Caller ID') }}</th><th>{{ __('Server Name') }}</th><th>{{ __('Logout Time') }}</th><th>{{ __('User Status') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Action') }}</th><th>{{ __('Export') }}</th></tr></thead>
                <tbody>
                    @forelse($clients as $client)
                        <tr>
                            <td class="fw-semibold">{{ $client->username }}</td><td>••••••••</td><td>{{ strtolower($client->service ?: 'pppoe') }}</td><td>{{ $client->profile ?: 'N/A' }}</td><td>{{ $client->caller_id ?: 'N/A' }}</td><td>{{ $client->router_name }}</td><td>{{ is_object($client->last_logged_out) ? $client->last_logged_out->format('d/m/Y h:i A') : ($client->last_logged_out ? date('d/m/Y h:i A', strtotime($client->last_logged_out)) : 'N/A') }}</td>
                            <td><span class="badge bg-warning text-dark">{{ $client->status ?: 'Unique' }}</span></td><td>{{ $client->customer?->branch ?: 'N/A' }}</td>
                            <td><button wire:click="toggle({{ $client->id }})" class="btn btn-sm {{ in_array(strtolower((string)$client->status), ['disabled','inactive']) ? 'btn-secondary' : 'btn-primary' }}">{{ in_array(strtolower((string)$client->status), ['disabled','inactive']) ? 'Enable' : 'Disable' }}</button></td>
                            <td><button wire:click="exportClientList" class="btn btn-sm btn-link"><i class="bi bi-person-plus-fill"></i></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">{{ __('No MikroTik clients found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $clients->links() }}</div>
    </div>
</div>
