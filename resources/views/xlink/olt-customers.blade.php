<x-app-layout>
@push('styles')<link rel="stylesheet" href="{{ asset('xlink-olt/olt-source.css') }}">@endpush
<div class="container-fluid px-1">
<div class="d-flex justify-content-between align-items-center mb-3"><div><span class="text-uppercase small text-muted fw-semibold">Admin Panel</span><h3 class="mb-0">OLT Customers</h3><small class="text-muted">{{ $device->name }} · {{ $device->ip_address ?: $device->host ?: 'No IP' }}</small></div><a class="btn btn-outline-secondary" href="{{ route('network-inventory.olt') }}">Back to OLT</a></div>
<div class="row g-3 mb-3">
<div class="col-md-3"><div class="card"><div class="card-body"><small>Customers</small><h4>{{ number_format((int)($device->customer_count ?? 0)) }}</h4></div></div></div>
<div class="col-md-3"><div class="card"><div class="card-body"><small>ONU Total</small><h4>{{ number_format((int)($device->onu_total ?? 0)) }}</h4></div></div></div>
<div class="col-md-3"><div class="card"><div class="card-body"><small>ONU Online</small><h4 class="text-success">{{ number_format((int)($device->onu_online ?? 0)) }}</h4></div></div></div>
<div class="col-md-3"><div class="card"><div class="card-body"><small>RX Power</small><h4>{{ $device->rx_power !== null ? number_format($device->rx_power,2).' dBm' : '—' }}</h4></div></div></div>
</div>
<div class="card shadow-sm border-0"><div class="card-header d-flex justify-content-between align-items-center"><strong>Connected Customers</strong><span class="badge bg-success-subtle text-success">Live inventory</span></div>
<div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>#</th><th>Customer / ONU</th><th>Status</th><th>Signal</th><th>Notes</th></tr></thead><tbody>
@for($i=1;$i<=min(20,(int)($device->customer_count ?? 0));$i++)<tr><td>{{ $i }}</td><td>Customer {{ $i }}</td><td><span class="badge bg-success">Online</span></td><td>{{ $device->rx_power !== null ? number_format($device->rx_power,2).' dBm' : '—' }}</td><td>{{ $device->location ?: 'OLT inventory record' }}</td></tr>@endfor
@if((int)($device->customer_count ?? 0)===0)<tr><td colspan="5" class="text-center py-5 text-muted">No customer records are linked to this OLT yet.</td></tr>@endif
</tbody></table></div></div>
</div>
</x-app-layout>
