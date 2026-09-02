<x-app-layout>
<div class="container-fluid px-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-chat-dots me-2"></i>Communication Center</h3>
            <div class="text-muted">Central hub for customer chat, SMS, notifications and WhatsApp.</div>
        </div>
        <span class="badge bg-success-subtle text-success">Live</span>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-sm {{($tab??'dashboard')==='dashboard'?'btn-primary':'btn-outline-primary'}}" href="{{route('xlink.communication-center')}}">Overview</a>
        <a class="btn btn-sm {{($tab??'')==='chat'?'btn-primary':'btn-outline-primary'}}" href="{{route('communication-center.chat')}}">Chat</a>
        <a class="btn btn-sm btn-outline-primary" href="{{route('communication-center.sms')}}">SMS Center</a>
        <a class="btn btn-sm btn-outline-primary" href="{{route('communication-center.notifications')}}">Notifications</a>
        <a class="btn btn-sm {{($tab??'')==='settings'?'btn-primary':'btn-outline-primary'}}" href="{{route('communication-center.settings')}}">Communication Settings</a>
        <a class="btn btn-sm {{($tab??'')==='whatsapp'?'btn-primary':'btn-outline-primary'}}" href="{{route('communication-center.whatsapp')}}">WhatsApp Inbox</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{route('sms-bridge.index')}}">SMS Bridge</a>
    </div>

    @if(session('communication_message'))
        <div class="alert alert-success">{{session('communication_message')}}</div>
    @endif

    @if(($tab??'dashboard')==='dashboard')
        <div class="row g-3 mb-4">
            @foreach([
                ['SMS Templates',$templates,'bi-chat-square-text'],
                ['Notifications',$notifications,'bi-bell'],
                ['Unread Alerts',$unread,'bi-bell-fill'],
                ['Conversations',$conversations,'bi-chat-left-text'],
                ['Open Support',$openTickets,'bi-life-preserver'],
                ['WhatsApp',$whatsapp?'Configured':'Not Configured','bi-whatsapp'],
            ] as $card)
                <div class="col-sm-6 col-xl-2">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-body">
                            <div class="text-muted small"><i class="bi {{$card[2]}} me-1"></i>{{$card[0]}}</div>
                            <div class="fs-4 fw-bold mt-2">{{$card[1]}}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">SMS Center</h5><p class="text-muted small">Existing SMS gateway, templates and bulk messaging remain on the production flow.</p><a class="btn btn-primary btn-sm" href="{{route('communication-center.sms')}}">Open SMS Center</a></div></div></div>
            <div class="col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">Customer Chat</h5><p class="text-muted small">Customer conversations use the existing support-ticket workflow.</p><a class="btn btn-outline-primary btn-sm" href="{{route('communication-center.chat')}}">Open Chat</a></div></div></div>
            <div class="col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">Notifications</h5><p class="text-muted small">Review, filter and mark application notifications as read.</p><a class="btn btn-outline-primary btn-sm" href="{{route('communication-center.notifications')}}">Open Notifications</a></div></div></div>
            <div class="col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">WhatsApp</h5><p class="text-muted small">Inbox is available now; provider configuration is tracked here.</p><a class="btn btn-outline-primary btn-sm" href="{{route('communication-center.whatsapp')}}">Open WhatsApp Inbox</a></div></div></div>
        </div>

        <div class="row g-3">
            <div class="col-xl-6">
                <div class="card shadow-sm h-100"><div class="card-header fw-bold">Recent Notifications</div>
                    <div class="list-group list-group-flush">
                        @forelse($recentNotifications as $n)
                            <div class="list-group-item"><div class="d-flex justify-content-between"><strong>{{ $n->title }}</strong><span class="badge bg-light text-dark">{{ $n->status }}</span></div><div class="small text-muted mt-1">{{ \Illuminate\Support\Str::limit($n->message, 120) }}</div><div class="small text-muted mt-1">{{ $n->created_at?->diffForHumans() }}</div></div>
                        @empty
                            <div class="list-group-item text-muted">No notifications found.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card shadow-sm h-100"><div class="card-header fw-bold">Recent Conversations</div>
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ticket</th><th>Customer</th><th>Subject</th><th>Status</th></tr></thead><tbody>
                        @forelse($recentConversations as $t)
                            <tr><td><code>{{$t->ticket_no}}</code></td><td>{{optional($t->customer)->name ?? $t->customer_unique_id}}</td><td>{{\Illuminate\Support\Str::limit($t->subject, 34)}}</td><td><span class="badge bg-secondary">{{$t->status}}</span></td></tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No conversations found.</td></tr>
                        @endforelse
                    </tbody></table></div>
                </div>
            </div>
        </div>

    @elseif(($tab??'')==='chat' || ($tab??'')==='whatsapp')
        <div class="card shadow-sm"><div class="card-header fw-bold">{{($tab==='chat')?'Customer Chat':'WhatsApp Inbox'}}</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ticket</th><th>Customer</th><th>Subject</th><th>Category</th><th>Status</th><th>Priority</th><th>Updated</th></tr></thead><tbody>
            @forelse($tickets as $t)
                <tr><td><code>{{$t->ticket_no}}</code></td><td>{{optional($t->customer)->name ?? $t->customer_unique_id}}</td><td>{{$t->subject}}</td><td>{{$t->category?:'—'}}</td><td><span class="badge bg-secondary">{{$t->status}}</span></td><td><span class="badge bg-light text-dark">{{$t->priority}}</span></td><td>{{$t->updated_at?->diffForHumans()}}</td></tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No conversations found.</td></tr>
            @endforelse
        </tbody></table></div><div class="card-footer">{{$tickets->links()}}</div></div>

    @else
        <div class="row g-3"><div class="col-xl-7"><div class="card shadow-sm"><div class="card-header fw-bold">Communication Settings</div><div class="card-body"><form method="POST" action="{{route('communication-center.settings.update')}}">@csrf
            <div class="mb-3"><label class="form-label">WhatsApp Business Number</label><input name="whatsapp" class="form-control" value="{{$whatsapp??''}}" placeholder="8801XXXXXXXXX"></div>
            <div class="mb-3"><label class="form-label">Notification Email</label><input type="email" name="notification_email" class="form-control" value="{{\App\Models\MainSiteData::where('key','notification_email')->value('value')}}" placeholder="noc@example.com"></div>
            <div class="mb-3"><label class="form-label">Notification Destination URL</label><input type="url" name="notification_url" class="form-control" value="{{\App\Models\MainSiteData::where('key','notification_url')->value('value')}}" placeholder="https://..."></div>
            <button class="btn btn-primary">Save Communication Settings</button>
        </form></div></div></div><div class="col-xl-5"><div class="card shadow-sm"><div class="card-body"><h5 class="fw-bold">Production Channels</h5><div class="list-group list-group-flush">
            <a class="list-group-item list-group-item-action" href="{{route('communication-center.sms')}}">SMS Center <span class="float-end">→</span></a>
            <a class="list-group-item list-group-item-action" href="{{route('sms-bridge.index')}}">SMS Bridge <span class="float-end">→</span></a>
            <a class="list-group-item list-group-item-action" href="{{route('communication-center.notifications')}}">Notifications <span class="float-end">→</span></a>
            <a class="list-group-item list-group-item-action" href="{{route('communication-center.whatsapp')}}">WhatsApp Inbox <span class="float-end">→</span></a>
        </div></div></div></div></div>
    @endif
</div>
</x-app-layout>
