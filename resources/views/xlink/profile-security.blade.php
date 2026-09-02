<x-app-layout>
<div class="container-fluid px-1">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h3 class="mb-1"><i class="bi bi-shield-lock me-2"></i>Profile & Security</h3><div class="text-muted">Manage your profile, password, two-factor authentication and active sessions.</div></div>
        <span class="badge bg-success-subtle text-success">Protected</span>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="small text-muted"><i class="bi bi-person me-1"></i>Account</div><div class="fw-bold fs-5 mt-2">{{ $user->name }}</div><div class="small text-muted">{{ $user->email }}</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="small text-muted"><i class="bi bi-shield-check me-1"></i>2FA</div><div class="fw-bold fs-5 mt-2">{{ $twoFactorEnabled ? 'Enabled' : 'Disabled' }}</div><div class="small text-muted">Account authentication</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="small text-muted"><i class="bi bi-key me-1"></i>API Tokens</div><div class="fw-bold fs-5 mt-2">{{ $tokenCount }}</div><div class="small text-muted">Personal access tokens</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="small text-muted"><i class="bi bi-clock-history me-1"></i>Sessions</div><div class="fw-bold fs-5 mt-2">Active</div><div class="small text-muted">Browser session controls</div></div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">My Profile</h5><p class="text-muted small">Update account information and profile photo.</p><a class="btn btn-primary btn-sm" href="{{ route('profile.show') }}">Open Profile</a></div></div></div>
        <div class="col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">Password</h5><p class="text-muted small">Change your account password securely.</p><a class="btn btn-outline-primary btn-sm" href="{{ route('profile.show') }}#profile-change-password">Change Password</a></div></div></div>
        <div class="col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">Two-Factor Authentication</h5><p class="text-muted small">Use the existing Fortify 2FA controls for account protection.</p><a class="btn btn-outline-primary btn-sm" href="{{ route('profile.show') }}#profile-two-factor">Manage 2FA</a></div></div></div>
        <div class="col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">API Tokens</h5><p class="text-muted small">Create and revoke personal API tokens.</p><a class="btn btn-outline-primary btn-sm" href="{{ route('api-tokens.index') }}">Manage Tokens</a></div></div></div>
        <div class="col-xl-6"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">Browser Sessions</h5><p class="text-muted small">Review and terminate other authenticated browser sessions.</p><a class="btn btn-outline-secondary btn-sm" href="{{ route('profile.show') }}#profile-browser-sessions">Manage Sessions</a></div></div></div>
        <div class="col-xl-6"><div class="card shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">Login Security</h5><p class="text-muted small">Review authentication events and login activity.</p><a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.login-logs') }}">View Login Logs</a></div></div></div>
    </div>
</div>
</x-app-layout>
