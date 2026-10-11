<x-app-layout>
@push('styles')
<link rel="stylesheet" href="{{ asset('xlink-broadband/customers.css') }}">
<link rel="stylesheet" href="{{ asset('xlink-broadband/ui-polish-20260826.css') }}">
<style>
.broadband-customer-table th{white-space:nowrap;font-size:.78rem;text-transform:uppercase;letter-spacing:.03em}
.broadband-customer-table td{vertical-align:middle}
.customer-actions{min-width:145px}.customer-actions form{display:inline}
.customer-actions .btn{margin:.12rem}.customer-diagnostics{display:inline-flex;align-items:center;gap:.15rem;flex-wrap:wrap}.customer-diagnostic-result{font-size:.75rem;min-height:1.1rem}.traffic-metric{border:1px solid var(--bs-border-color);border-radius:.6rem;padding:.8rem 1rem}.traffic-metric small{display:block;color:var(--bs-secondary-color)}.traffic-metric strong{font-size:1.25rem}.traffic-live-dot{display:inline-block;width:.5rem;height:.5rem;border-radius:50%;background:currentColor;box-shadow:0 0 0 .25rem color-mix(in srgb,currentColor 12%,transparent)}
.customer-meta{font-size:.78rem}.filter-card .form-label{font-size:.78rem;font-weight:600;color:#6c757d}
</style>
@endpush

@php
    $title = match($mode) {
        'due' => 'Due Customers',
        'inactive' => 'Inactive Customers',
        'unverified' => 'Unverified Customers',
        'online' => 'Online Customers',
        default => 'Customer List',
    };
@endphp

<div class="container-fluid py-2">
    @if(session('broadband_message'))
        <div class="alert alert-success py-2">{{ session('broadband_message') }}</div>
    @endif
    @if($errors->has('customer'))
        <div class="alert alert-danger py-2">{{ $errors->first('customer') }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <span class="text-uppercase small text-muted fw-semibold">Broadband</span>
            <h3 class="mb-0">{{ $title }}</h3>
            <small class="text-muted">Search and manage broadband customers without changing router configuration unless an action is explicitly requested.</small>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="{{ route('customer-add') }}"><i class="bi bi-person-plus me-1"></i>Add Customer</a>
            <a class="btn btn-primary" href="{{ route('broadband-packages') }}"><i class="bi bi-speedometer2 me-1"></i>Packages</a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3 filter-card">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-12 col-lg-5">
                    <label class="form-label mb-1">Search customer</label>
                    <input class="form-control" name="q" value="{{ request('q') }}" placeholder="CID, name, mobile, username, IP, NID, address, MAC/caller ID, router or package">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label mb-1">Router</label>
                    <select class="form-select" name="router_id">
                        <option value="">All Routers</option>
                        @foreach($routers as $r)
                            <option value="{{ $r->router_name }}" @selected(request('router_id') === $r->router_name)>{{ $r->router_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label mb-1">Rows</label>
                    <select class="form-select" name="rows">
                        @foreach([50,100,250,500] as $size)
                            <option value="{{ $size }}" @selected((int)request('rows',50) === $size)>{{ $size }} rows</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-3 d-flex align-items-end gap-2">
                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Search</button>
                    <a class="btn btn-outline-secondary" href="{{ url()->current() }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <strong>Customer List</strong>
            <span class="text-muted small">{{ number_format($customers->total()) }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 broadband-customer-table">
                <thead>
                    <tr>
                        <th>CID</th><th>Customer</th><th>Connection</th><th>Mobile</th><th>Package</th>
                        <th>Router</th><th>Billing</th><th>Address / Area</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($customers as $c)
                    @php
                        $customerId = encrypt($c->customer_unique_id);
                        $address = $c->customerAddress->map(fn($a) => implode(', ', array_filter([$a->input_type_text, $a->input_type_dropdown, $a->input_type_textarea])))->filter()->implode(', ');
                        $connection = $c->pppUser->username ?? $c->pppUser->ppp_remote_ip ?? '—';
                        $status = $c->status ?: 'unknown';
                        $statusClass = in_array($status, ['active','online']) ? 'success' : (in_array($status, ['inactive','disable']) ? 'secondary' : 'warning');
                    @endphp
                    <tr>
                        <td><a class="fw-semibold text-decoration-none" href="{{ route('customers.show', $customerId) }}">{{ $c->customer_unique_id }}</a></td>
                        <td>
                            <strong>{{ $c->customer_name ?: 'Unnamed' }}</strong>
                            <div class="customer-meta text-muted">{{ $c->email ?: 'No email' }}</div>
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $c->pppUser?->service === 'static' ? 'info' : 'secondary' }}">{{ strtoupper($c->pppUser?->service ?: 'N/A') }}</span>
                            <div class="customer-meta">{{ $connection }}</div>
                        </td>
                        <td>{{ $c->mobile ?: '—' }}</td>
                        <td>{{ $c->package->package ?? $c->pppUser->package_name ?? '—' }}</td>
                        <td>{{ $c->pppUser->router_name ?? '—' }}</td>
                        <td>
                            <div>Bill: {{ number_format($c->billing?->total_amount ?? 0, 2) }} ৳</div>
                            <div class="{{ ($c->billing?->due_amount ?? 0) > 0 ? 'text-danger' : 'text-success' }}">Due: {{ number_format($c->billing?->due_amount ?? 0, 2) }} ৳</div>
                        </td>
                        <td>
                            <div>{{ $address ?: ($c->address ?: '—') }}</div>
                            @if($c->pppUser?->router_name)<div class="customer-meta text-muted">{{ $c->pppUser->router_name }}</div>@endif
                        </td>
                        <td><span class="badge text-bg-{{ $statusClass }}">{{ ucfirst($status) }}</span></td>
                        <td class="customer-actions">
                            <div class="customer-diagnostics">
                                <a class="btn btn-outline-secondary btn-sm" title="View" href="{{ route('customers.show', $customerId) }}"><i class="bi bi-eye"></i></a>
                                @if($mode !== 'online' && (auth()->user()->hasRole('Super Admin') || hasAccess(['Super Admin'], ['edit-customer'])))
                                    <a class="btn btn-primary btn-sm" title="Edit" href="{{ route('customers.edit', $customerId) }}"><i class="bi bi-pencil-square"></i></a>
                                @endif
                                @if($mode === 'online')
                                    <button type="button" class="btn btn-outline-info btn-sm js-customer-ping" title="Ping Check" data-url="{{ route('broadband-customer-ping', $customerId) }}">
                                        <i class="bi bi-broadcast-pin"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm js-customer-traffic" title="Real-Time Traffic" data-url="{{ route('broadband-customer-traffic', $customerId) }}" data-customer="{{ $c->customer_unique_id }}" data-username="{{ $c->pppUser->username ?? '—' }}">
                                        <i class="bi bi-activity"></i>
                                    </button>
                                @endif
                                @if($status === 'active' && (auth()->user()->hasRole('Super Admin') || hasAccess(['Super Admin'], ['disable-customer'])))
                                    <form method="POST" action="{{ route('broadband-customer-disable', $customerId) }}" onsubmit="return confirm('Disable this customer?')">
                                        @csrf
                                        <button class="btn btn-warning btn-sm" title="Disable"><i class="bi bi-pause-circle"></i></button>
                                    </form>
                                @endif
                                @if($status !== 'active' && (auth()->user()->hasRole('Super Admin') || hasAccess(['Super Admin'], ['delete-customer'])))
                                    <form method="POST" action="{{ route('broadband-customer-destroy', $customerId) }}" onsubmit="return confirm('Delete this customer record? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </div>
                            @if($mode === 'online')
                                <div class="customer-diagnostic-result text-muted js-ping-result" aria-live="polite"></div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center py-5 text-muted">No customers found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-muted small">Showing {{ $customers->firstItem() ?: 0 }}–{{ $customers->lastItem() ?: 0 }} of {{ $customers->total() }}</span>
            {{ $customers->links() }}
        </div>
    </div>
@if($mode === 'online')
<div class="modal fade" id="customerTrafficModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <div>
                    <div class="text-uppercase small text-muted fw-semibold">Broadband</div>
                    <h5 class="modal-title mb-0"><span id="trafficCustomer">Customer</span> · Live Traffic</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="small text-muted">PPPoE: <strong id="trafficUsername">—</strong></div>
                    <div class="small text-muted">Router: <strong id="trafficRouter">—</strong></div>
                </div>
                <div id="trafficError" class="alert alert-warning py-2 d-none"></div>
                <div class="row g-2">
                    <div class="col-6"><div class="traffic-metric text-success"><small>Download / RX</small><strong id="trafficRx">0 bps</strong></div></div>
                    <div class="col-6"><div class="traffic-metric text-primary"><small>Upload / TX</small><strong id="trafficTx">0 bps</strong></div></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 small text-muted">
                    <span><span class="traffic-live-dot text-success"></span> Live</span>
                    <span id="trafficUpdated">Waiting for data…</span>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const trafficModal = document.getElementById('customerTrafficModal');
    const rxEl = document.getElementById('trafficRx');
    const txEl = document.getElementById('trafficTx');
    const updatedEl = document.getElementById('trafficUpdated');
    const errorEl = document.getElementById('trafficError');
    const customerEl = document.getElementById('trafficCustomer');
    const usernameEl = document.getElementById('trafficUsername');
    const routerEl = document.getElementById('trafficRouter');

    const formatBits = (bits) => {
        const value = Number(bits || 0);
        if (value >= 1000000000) return (value / 1000000000).toFixed(2) + ' Gbps';
        if (value >= 1000000) return (value / 1000000).toFixed(2) + ' Mbps';
        if (value >= 1000) return (value / 1000).toFixed(2) + ' Kbps';
        return Math.round(value) + ' bps';
    };

    let trafficUrl = '';
    let trafficTimer = null;
    let trafficInFlight = false;

    const stopTraffic = () => {
        if (trafficTimer) {
            clearInterval(trafficTimer);
            trafficTimer = null;
        }
        trafficInFlight = false;
    };

    const pollTraffic = async () => {
        if (!trafficUrl || trafficInFlight) return;
        trafficInFlight = true;
        try {
            const response = await fetch(trafficUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'Live traffic is unavailable.');
            rxEl.textContent = formatBits(data.rx_bps);
            txEl.textContent = formatBits(data.tx_bps);
            routerEl.textContent = data.router || '—';
            updatedEl.textContent = 'Updated ' + new Date().toLocaleTimeString();
            errorEl.classList.add('d-none');
            errorEl.textContent = '';
        } catch (error) {
            errorEl.textContent = error.message || 'Live traffic is unavailable.';
            errorEl.classList.remove('d-none');
        } finally {
            trafficInFlight = false;
        }
    };

    document.querySelectorAll('.js-customer-ping').forEach((button) => {
        button.addEventListener('click', async () => {
            const result = button.closest('td').querySelector('.js-ping-result');
            const original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';
            try {
                const response = await fetch(button.dataset.url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });
                const data = await response.json();
                if (data.ok) {
                    result.className = 'customer-diagnostic-result text-success js-ping-result';
                    result.textContent = 'Ping: ' + Number(data.latency_ms || 0).toFixed(2) + ' ms';
                    result.title = data.ip || '';
                } else {
                    result.className = 'customer-diagnostic-result text-danger js-ping-result';
                    result.textContent = data.message || 'Ping failed';
                }
            } catch (error) {
                result.className = 'customer-diagnostic-result text-danger js-ping-result';
                result.textContent = error.message || 'Ping check failed';
            } finally {
                button.disabled = false;
                button.innerHTML = original;
            }
        });
    });

    document.querySelectorAll('.js-customer-traffic').forEach((button) => {
        button.addEventListener('click', () => {
            trafficUrl = button.dataset.url || '';
            customerEl.textContent = button.dataset.customer || 'Customer';
            usernameEl.textContent = button.dataset.username || '—';
            routerEl.textContent = 'Loading…';
            rxEl.textContent = '0 bps';
            txEl.textContent = '0 bps';
            updatedEl.textContent = 'Waiting for data…';
            errorEl.classList.add('d-none');

            if (window.bootstrap && trafficModal) {
                window.bootstrap.Modal.getOrCreateInstance(trafficModal).show();
            }

            stopTraffic();
            pollTraffic();
            trafficTimer = setInterval(pollTraffic, 2000);
        });
    });

    if (trafficModal) trafficModal.addEventListener('hidden.bs.modal', stopTraffic);
})();
</script>
@endpush
@endif
</div>
</x-app-layout>
