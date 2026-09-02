<x-app-layout>
<div class="container-fluid px-1 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4"><div><h3 class="mb-1"><i class="bi bi-people me-2"></i>Team & Access</h3><div class="text-muted">Users, roles, authentication and access administration.</div></div><span class="badge bg-success-subtle text-success">Protected</span></div>
    <div class="row g-3 mb-4">
        @foreach ([['Users',$users,'bi-people-fill','primary'],['Roles',$roles,'bi-shield-lock-fill','success'],['Super Admins',$superAdmins,'bi-star-fill','warning'],['2FA Enabled',$twoFactor,'bi-shield-check','info']] as [$label,$value,$icon,$tone])
            <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body d-flex align-items-center gap-3"><div class="rounded-circle bg-{{$tone}}-subtle text-{{$tone}} p-3"><i class="bi {{$icon}} fs-5"></i></div><div><small class="text-muted">{{$label}}</small><div class="fs-3 fw-bold">{{$value}}</div></div></div></div></div>
        @endforeach
    </div>
    <div class="row g-3 mb-4">
        @foreach ([['Manage Users','admin-users','User Directory','Create, edit and review admin users.','bi-person-gear'],['Manage Roles','admin-roles','Role Management','Assign roles and granular permissions.','bi-shield-lock'],['Login Logs','admin.login-logs','Authentication History','Review login activity and security events.','bi-clock-history'],['My Profile','profile.show','Account Security','Profile, password, 2FA and browser sessions.','bi-person-badge']] as [$label,$route,$title,$hint,$icon])
            <div class="col-md-6 col-xl-3"><a href="{{route($route)}}" wire:navigate.hover class="card h-100 shadow-sm text-decoration-none"><div class="card-body"><div class="d-flex align-items-center gap-2 mb-2"><i class="bi {{$icon}} fs-4 text-primary"></i><h5 class="mb-0 text-dark">{{$label}}</h5></div><div class="fw-semibold text-dark">{{$title}}</div><small class="text-muted">{{$hint}}</small></div></a></div>
        @endforeach
    </div>
    <div class="card shadow-sm"><div class="card-header fw-bold">Recent User Accounts</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>User</th><th>Email</th><th>Role</th><th>2FA</th><th>Joined</th></tr></thead><tbody>
        @forelse($recentUsers as $user)
            <tr><td class="fw-semibold">{{$user->name}}</td><td>{{$user->email}}</td><td>{{implode(', ', $user->getRoleNames()->all()) ?: 'No Role'}}</td><td>@if($user->two_factor_confirmed_at)<span class="badge bg-success">Enabled</span>@else<span class="badge bg-secondary">Not enabled</span>@endif</td><td>{{$user->created_at?->format('d M Y')}}</td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No users found.</td></tr>
        @endforelse
    </tbody></table></div></div>
</div>
</x-app-layout>
