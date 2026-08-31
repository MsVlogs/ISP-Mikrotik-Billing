<x-app-layout>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-1"><i class="bi bi-grid-3x3-gap me-2"></i>X-Link Billing Control Center</h3>
      <p class="text-muted mb-0">Operational overview and module access.</p>
    </div>
    <span class="badge bg-success">Live</span>
  </div>

  <div class="row g-3 mb-4">
    @foreach($kpis as $kpi)
      <div class="col-sm-6 col-xl">
        <div class="card h-100 shadow-sm">
          <div class="card-body d-flex align-items-center gap-3">
            <span class="rounded-circle bg-light p-3"><i class="bi {{ $kpi['icon'] }} fs-4"></i></span>
            <div>
              <div class="small text-muted">{{ $kpi['label'] }}</div>
              <div class="fs-4 fw-bold">{{ number_format($kpi['value']) }}</div>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card shadow-sm">
    <div class="card-header"><h5 class="mb-0">Modules</h5></div>
    <div class="card-body">
      <div class="row g-3">
        @foreach($modules as $module)
          <div class="col-sm-6 col-lg-3">
            <a wire:navigate.hover href="{{ route($module[1]) }}" class="card h-100 text-decoration-none">
              <div class="card-body">
                <i class="bi {{ $module[2] }} fs-3"></i>
                <h6 class="mt-3 mb-1">{{ $module[0] }}</h6>
                <small class="text-muted">Open module</small>
              </div>
            </a>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</div>
</x-app-layout>
