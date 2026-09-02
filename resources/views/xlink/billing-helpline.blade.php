<x-app-layout>
<div class="container-fluid px-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h3 class="mb-1"><i class="bi bi-headset me-2"></i>Billing Helpline</h3><div class="text-muted">Central billing support and customer issue escalation desk.</div></div>
        <a class="btn btn-primary btn-sm" href="{{ route('admin-tickets') }}"><i class="bi bi-ticket-detailed me-1"></i>Manage Tickets</a>
    </div>
    <div class="row g-3 mb-4">
        @foreach([
            ['Open',$open,'bi-folder2-open','warning'],['In Progress',$inProgress,'bi-clock-history','primary'],['Resolved',$resolved,'bi-check-circle','success'],['High Priority',$highPriority,'bi-exclamation-triangle','danger'],['Total',$total,'bi-ticket-perforated','secondary']
        ] as [$label,$value,$icon,$tone])
        <div class="col-sm-6 col-xl">
            <div class="card shadow-sm h-100 border-0"><div class="card-body d-flex align-items-center gap-3"><div class="rounded-3 bg-{{ $tone }}-subtle text-{{ $tone }} p-3"><i class="bi {{ $icon }} fs-4"></i></div><div><div class="small text-muted">{{ $label }}</div><div class="fs-4 fw-bold">{{ $value }}</div></div></div></div>
        </div>
        @endforeach
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">Support Desk</h5><p class="text-muted small">Review, reply and resolve customer billing tickets.</p><a class="btn btn-outline-primary btn-sm" href="{{ route('admin-tickets') }}">Support Tickets</a></div></div></div>
        <div class="col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">Payment Collection</h5><p class="text-muted small">Open the collection workflow for customer payments.</p><a class="btn btn-outline-primary btn-sm" href="{{ route('payment-collection') }}">Payment Collection</a></div></div></div>
        <div class="col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">Collection Reports</h5><p class="text-muted small">Track payment activity and collection performance.</p><a class="btn btn-outline-primary btn-sm" href="{{ route('collection-report.index') }}">Collection Report</a></div></div></div>
        <div class="col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">Customer Summary</h5><p class="text-muted small">Inspect customer-level billing history and balances.</p><a class="btn btn-outline-primary btn-sm" href="{{ route('customer-summary') }}">Customer Summary</a></div></div></div>
    </div>
    <div class="card shadow-sm border-0"><div class="card-header fw-bold">Recent Billing Tickets</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ticket</th><th>Customer</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Updated</th></tr></thead><tbody>
    @forelse($recentTickets as $t)
        <tr><td><code>#{{ $t->ticket_no }}</code></td><td>{{ $t->customer->customer_name ?? $t->customer_unique_id }}</td><td>{{ \Illuminate\Support\Str::limit($t->subject, 48) }}</td><td>{{ $t->category ?: '—' }}</td><td><span class="badge bg-{{ $t->priority==='high'?'danger':'light text-dark' }}">{{ ucfirst($t->priority) }}</span></td><td><span class="badge bg-secondary">{{ ucfirst(str_replace('_',' ',$t->status)) }}</span></td><td>{{ $t->updated_at?->diffForHumans() }}</td></tr>
    @empty
        <tr><td colspan="7" class="text-center text-muted py-4">No billing tickets found.</td></tr>
    @endforelse
    </tbody></table></div></div>
</div>
</x-app-layout>
