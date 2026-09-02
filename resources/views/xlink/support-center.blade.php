<x-app-layout>
<div class="container-fluid px-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h3 class="mb-1"><i class="bi bi-life-preserver me-2"></i>Support Center</h3><div class="text-muted">Customer support desk, ticket queue and operational activity.</div></div>
        <span class="badge bg-success-subtle text-success">Live</span>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><small class="text-muted">Open Tickets</small><div class="fs-3 fw-bold text-danger mt-2">{{ $open }}</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><small class="text-muted">In Progress</small><div class="fs-3 fw-bold mt-2">{{ $inProgress }}</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><small class="text-muted">Resolved</small><div class="fs-3 fw-bold text-success mt-2">{{ $resolved }}</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><small class="text-muted">Total Tickets</small><div class="fs-3 fw-bold mt-2">{{ $total }}</div></div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-8"><div class="card shadow-sm"><div class="card-header fw-bold">Recent Support Tickets</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ticket</th><th>Customer</th><th>Subject</th><th>Priority</th><th>Status</th><th>Created</th></tr></thead><tbody>
        @forelse($tickets as $ticket)<tr><td><strong>{{ $ticket->ticket_no }}</strong></td><td>{{ $ticket->customer->customer_name ?? $ticket->customer_unique_id }}</td><td>{{ \Illuminate\Support\Str::limit($ticket->subject, 42) }}</td><td><span class="badge bg-{{ $ticket->priority === 'high' ? 'danger' : ($ticket->priority === 'medium' ? 'warning' : 'success') }}">{{ ucfirst($ticket->priority) }}</span></td><td><span class="badge bg-{{ $ticket->status === 'resolved' ? 'success' : ($ticket->status === 'closed' ? 'secondary' : ($ticket->status === 'in_progress' ? 'primary' : 'danger')) }}">{{ ucfirst(str_replace('_',' ',$ticket->status)) }}</span></td><td>{{ $ticket->created_at->format('d M H:i') }}</td></tr>@empty
        <tr><td colspan="6" class="text-center text-muted py-4">No support tickets yet.</td></tr>@endforelse</tbody></table></div></div></div>
        <div class="col-xl-4"><div class="card shadow-sm"><div class="card-header fw-bold">Support Operations</div><div class="card-body d-grid gap-2">
            <a class="btn btn-primary" href="{{ route('admin-tickets') }}"><i class="bi bi-ticket-detailed me-1"></i>Manage Support Tickets</a>
            <a class="btn btn-outline-primary" href="{{ route('admin.activity-logs') }}"><i class="bi bi-clock-history me-1"></i>Activity Logs</a>
            <a class="btn btn-outline-secondary" href="{{ route('admin.login-logs') }}"><i class="bi bi-shield-check me-1"></i>Login Logs</a>
            <a class="btn btn-outline-success" href="{{ route('notifications') }}"><i class="bi bi-bell me-1"></i>Notifications</a>
        </div></div></div>
    </div>
</div>
</x-app-layout>
