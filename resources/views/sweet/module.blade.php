<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div><h3 class="mb-1"><i class="bi {{ $icon ?? 'bi-grid' }} me-2"></i>{{ $title }}</h3><small class="text-muted">Sweet Billing module</small></div>
    <span class="badge bg-success">Ready</span>
  </div>
  <div class="row g-3 mb-4">
    @foreach(($stats ?? []) as $stat)
      <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ $stat[0] }}</small><h4 class="mt-2 mb-0">{{ $stat[1] }}</h4></div></div></div>
    @endforeach
  </div>
  <div class="card"><div class="card-body">
    <h5 class="mb-3">{{ $description }}</h5>
    <div class="row g-3">
      @foreach($links as $link)
        <div class="col-md-4"><a href="{{ $link[0] }}" wire:navigate.hover class="card h-100 text-decoration-none"><div class="card-body"><div class="fw-semibold">{{ $link[1] }}</div><small class="text-muted">{{ $link[2] }}</small></div></a></div>
      @endforeach
    </div>
  </div></div>
</div>
