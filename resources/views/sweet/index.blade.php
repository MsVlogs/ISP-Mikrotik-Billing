<div class="container-fluid py-4">
<h3 class="mb-1"><i class="bi bi-grid-3x3-gap me-2"></i>Sweet Billing Control Center</h3>
<p class="text-muted mb-4">All Sweet Billing operations in one place.</p>
<div class="row g-3">
@foreach($modules as $module)<div class="col-md-3"><a wire:navigate.hover href="{{ route($module[1]) }}" class="card h-100 text-decoration-none"><div class="card-body"><i class="bi {{ $module[2] }} fs-3"></i><h5 class="mt-3 mb-1">{{ $module[0] }}</h5><small class="text-muted">Open module</small></div></a></div>@endforeach
</div>
</div>
