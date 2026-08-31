<div class="container-fluid px-1">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div><h3 class="mb-1"><i class="bi {{ $icon ?? 'bi-hdd-network' }} me-2"></i>{{ $title }}</h3><div class="text-muted">{{ $description }}</div></div>
    <span class="badge bg-success-subtle text-success">{{ $status }}</span>
  </div>
  <div class="row g-3 mb-4">
    @foreach($stats as $stat)<div class="col-md-4"><div class="card h-100 shadow-sm"><div class="card-body"><small class="text-muted">{{ $stat[0] }}</small><div class="fs-3 fw-bold mt-2">{{ $stat[1] }}</div></div></div></div>@endforeach
  </div>
  <div class="card shadow-sm"><div class="card-body"><h5>Quick Management</h5><div class="row g-3 mt-1">
    @foreach($links as $link)<div class="col-md-6"><a wire:navigate.hover class="btn btn-outline-primary w-100 text-start" href="{{ route($link[0]) }}"><i class="bi bi-arrow-right-circle me-2"></i>{{ $link[1] }}<small class="d-block text-muted ms-4">{{ $link[2] }}</small></a></div>@endforeach
  </div></div></div>
</div>
