<x-app-layout>
<div class="container-fluid px-1">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h3 class="mb-1">Support General Settings</h3><div class="text-muted">Default workflow and notification controls.</div></div>
        <a href="{{ route('xlink.support-center') }}" class="btn btn-outline-secondary">Back to Support</a>
    </div>
    @if(session('support_message'))<div class="alert alert-success">{{ session('support_message') }}</div>@endif
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="post" action="{{ route('support-center.settings.save') }}" class="row g-3">
                @csrf
                <div class="col-md-4"><label class="form-label">Default Priority</label><select name="default_priority" class="form-select">@foreach(['low','medium','high','urgent'] as $p)<option value="{{ $p }}" @selected($settings['default_priority']===$p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Auto Close Days</label><input name="auto_close_days" type="number" min="0" max="365" value="{{ $settings['auto_close_days'] }}" class="form-control"><small class="text-muted">0 disables auto-close policy.</small></div>
                <div class="col-md-4 d-flex align-items-center"><label class="form-check"><input class="form-check-input" type="checkbox" name="notify_sms" value="1" @checked($settings['notify_sms'])><span class="form-check-label">Enable support SMS notifications</span></label></div>
                <div class="col-12 text-end"><button class="btn btn-primary px-4">Save Settings</button></div>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
