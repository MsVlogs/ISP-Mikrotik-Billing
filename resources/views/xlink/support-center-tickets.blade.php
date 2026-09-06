<x-app-layout>
<div class="container-fluid px-1">
    <div class="d-flex justify-content-between align-items-center mb-3"><h3 class="mb-0">Ticket List</h3><div class="d-flex gap-2"><a href="{{ route('support-center.tickets-export', request()->query()) }}" class="btn btn-outline-success">Download Tickets</a><a href="{{ route('support-center.create-ticket') }}" class="btn btn-primary">Add Ticket</a></div></div>
    @if(session('support_message'))<div class="alert alert-success">{{ session('support_message') }}</div>@endif
    <div class="card shadow-sm border-0 mb-3"><div class="card-body"><form class="row g-2">
        <div class="col-md-2"><label class="form-label small">From</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label small">To</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label small">Status</label><select name="status" class="form-select"><option value="">All Status</option>@foreach(['new','open','pending','in_progress','resolved','closed'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label small">Type</label><select name="ticket_type" class="form-select"><option value="">All Types</option>@foreach(['complain','task','sales','legacy_sales'] as $t)<option value="{{ $t }}" @selected(request('ticket_type')===$t)>{{ ucfirst(str_replace('_',' ',$t)) }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label small">Assigned</label><select name="assigned_to" class="form-select"><option value="">All Staff</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected(request('assigned_to')==$person->id)>{{ $person->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label small">Search</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Ticket, CID, customer"></div>
        <div class="col-12 text-end"><button class="btn btn-outline-primary px-4">Apply</button></div>
    </form></div></div>
    <div class="card shadow-sm border-0"><div class="card-body">
        <form method="post" action="{{ route('support-center.tickets-bulk') }}">@csrf
            <div class="row g-2 mb-3"><div class="col-md-3"><select name="status" class="form-select" required><option value="">Bulk action status…</option>@foreach(['new','open','pending','in_progress','resolved','closed'] as $s)<option value="{{ $s }}">{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select></div><div class="col-md-3"><button class="btn btn-outline-primary" onclick="return confirm('Update selected tickets?')">Update Selected</button></div></div>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.ticket-check').forEach(x=>x.checked=this.checked)"></th><th>Ticket</th><th>Customer</th><th>Type / Topic</th><th>Subject</th><th>Priority</th><th>Assigned</th><th>Uptime</th><th>Latest Update / Tracking</th><th>Action</th></tr></thead><tbody>
            @forelse($tickets as $t)<tr>
                <td><input class="ticket-check" type="checkbox" name="ticket_ids[]" value="{{ $t->id }}"></td>
                <td class="fw-bold text-primary">#{{ $t->ticket_no }}<br><small class="text-muted">{{ $t->created_at?->format('d M H:i') }}</small></td>
                <td>{{ $t->customer->customer_name ?? $t->customer_unique_id }}<br><small class="text-muted">{{ $t->customer_unique_id }}</small></td>
                <td>{{ ucfirst(str_replace('_',' ',$t->ticket_type)) }}<br><small class="text-muted">{{ $t->topic ?: $t->category ?: '—' }}</small></td>
                <td>{{ \Illuminate\Support\Str::limit($t->subject, 48) }}</td><td>{{ ucfirst($t->priority) }}</td>
                <td><select form="ticket-update-{{ $t->id }}" name="assigned_to" class="form-select form-select-sm"><option value="">Unassigned</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected($t->assigned_to===$person->id)>{{ $person->name }}</option>@endforeach</select></td>
                <td>{{ $t->customer?->pppUser?->uptime ?: '—' }}</td>
                <td>{{ $t->updated_at?->format('d M H:i') }}<br><small class="text-muted">{{ ucfirst($t->customer?->pppUser?->status ?? 'unknown') }}</small></td>
                <td><form id="ticket-update-{{ $t->id }}" method="post" action="{{ route('support-center.ticket-update',$t) }}" class="d-flex gap-1">@csrf @method('PUT')<select name="status" class="form-select form-select-sm">@foreach(['new','open','pending','in_progress','resolved','closed'] as $s)<option value="{{ $s }}" @selected($t->status===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select><input type="hidden" name="priority" value="{{ $t->priority }}"><button class="btn btn-sm btn-outline-primary">Save</button></form></td>
            </tr>@empty<tr><td colspan="10" class="text-center text-muted py-4">No tickets found.</td></tr>@endforelse
            </tbody></table></div>
        </form>
        {{ $tickets->links() }}
    </div></div>
</div></x-app-layout>