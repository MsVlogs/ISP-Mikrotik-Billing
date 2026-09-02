<x-app-layout>
<div class="container-fluid px-1">
    @if(session('support_message')) <div class="alert alert-success shadow-sm">{{ session('support_message') }}</div> @endif
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div><h3 class="mb-1"><i class="bi bi-life-preserver me-2"></i>Support Center</h3><div class="text-muted">Support operations, ticket queue, sales leads and KYC follow-up.</div></div>
        <div class="d-flex gap-2"><a href="{{ route('support-center.create-ticket') }}" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Ticket</a><a href="{{ route('support-center.sales-create') }}" class="btn btn-outline-primary"><i class="bi bi-person-plus me-1"></i>Sales Query</a></div>
    </div>
    <div class="row g-3 mb-4">
        @foreach([['Tickets',$stats['total'],'primary'],['New',$stats['new'],'info'],['Open Workload',$stats['open'],'warning'],['Closed',$stats['closed'],'success'],['Complaints',$stats['complain'],'danger'],['Tasks',$stats['task'],'secondary'],['Sales',$stats['sales'],'primary'],['Pending KYC',$stats['kyc'],'warning']] as [$label,$value,$color])
            <div class="col-6 col-md-3 col-xl-3"><div class="card shadow-sm h-100 border-0"><div class="card-body"><small class="text-muted">{{ $label }}</small><div class="fs-3 fw-bold text-{{ $color }} mt-1">{{ $value }}</div></div></div></div>
        @endforeach
    </div>
    <div class="card shadow-sm border-0 mb-4"><div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small">From Date</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label small">To Date</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label small">Status</label><select name="status" class="form-select"><option value="">All</option>@foreach(['new','open','pending','in_progress','resolved','closed'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small">Type</label><select name="type" class="form-select"><option value="">All</option><option value="complain">Complain</option><option value="task">Task</option><option value="sales">Sales</option></select></div>
            <div class="col-md-3"><label class="form-label small">Search</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Ticket, CID, customer or subject"></div>
            <div class="col-md-1"><button class="btn btn-outline-primary w-100">Filter</button></div>
        </form>
    </div></div>
    <div class="row g-3">
        <div class="col-xl-9"><div class="card shadow-sm border-0"><div class="card-header bg-white d-flex justify-content-between"><strong>Recent Tickets</strong><a href="{{ route('support-center.tickets') }}">View all</a></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ticket</th><th>Customer</th><th>Type / Topic</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Updated</th></tr></thead><tbody>@forelse($tickets as $ticket)<tr><td class="fw-bold text-primary">#{{ $ticket->ticket_no }}</td><td>{{ $ticket->customer->customer_name ?? $ticket->customer_unique_id }}<br><small class="text-muted">{{ $ticket->customer_unique_id }}</small></td><td>{{ ucfirst($ticket->ticket_type) }}<br><small class="text-muted">{{ $ticket->topic ?: $ticket->category }}</small></td><td>{{ ucfirst($ticket->priority) }}</td><td>{{ ucfirst(str_replace('_',' ',$ticket->status)) }}</td><td>{{ $ticket->assignee->name ?? 'Unassigned' }}</td><td>{{ $ticket->updated_at?->format('d M H:i') }}</td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">No tickets yet.</td></tr>@endforelse</tbody></table></div></div></div>
        <div class="col-xl-3"><div class="card shadow-sm border-0"><div class="card-header bg-white"><strong>Support Operations</strong></div><div class="card-body d-grid gap-2">
            <a class="btn btn-primary" href="{{ route('support-center.tickets') }}">Ticket List</a><a class="btn btn-outline-primary" href="{{ route('support-center.create-ticket') }}">Open New Ticket</a><a class="btn btn-outline-success" href="{{ route('support-center.sales') }}">Sales Query List</a><a class="btn btn-outline-success" href="{{ route('support-center.sales-create') }}">New Sales Query</a><a class="btn btn-outline-warning" href="{{ route('support-center.kyc') }}">KYC Update</a><a class="btn btn-outline-secondary" href="{{ route('support-center.templates') }}">Ticket Templates & Rules</a><a class="btn btn-outline-dark" href="{{ route('admin.activity-logs') }}">Activity Logs</a>
        </div></div></div>
    </div>
</div>
</x-app-layout>
