<x-app-layout>
<x-slot name="header"><div class="d-flex align-items-center gap-2"><i class="bi bi-diagram-3-fill text-success"></i><span>{{ __('Partner Network') }}</span></div></x-slot>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div><h3 class="mb-1">{{ __('Partner Network') }}</h3><div class="text-muted small">{{ __('Partners, cashflow, commission and ledger control center') }}</div></div>
    <a href="{{ route('admin.resellers.create') }}" class="btn btn-success"><i class="bi bi-person-plus me-1"></i>{{ __('Add Partner') }}</a>
  </div>
  <form class="card border-0 shadow-sm mb-3"><div class="card-body row g-2 align-items-end">
    <div class="col-sm-4 col-lg-3"><label class="form-label">{{ __('From Date') }}</label><input class="form-control" type="date" name="from" value="{{ $from }}"></div>
    <div class="col-sm-4 col-lg-3"><label class="form-label">{{ __('To Date') }}</label><input class="form-control" type="date" name="to" value="{{ $to }}"></div>
    <div class="col-sm-4 col-lg-2"><button class="btn btn-outline-success w-100"><i class="bi bi-funnel me-1"></i>{{ __('Apply') }}</button></div>
  </div></form>
  <div class="row g-3 mb-4">
    @foreach([['Partners',$stats['partners'],'bi-people'],['Active',$stats['active'],'bi-person-check'],['Customer Collection',$stats['collection'],'bi-cash-stack'],['Commission',$stats['commission'],'bi-graph-up-arrow'],['Payouts',$stats['payouts'],'bi-wallet2'],['Wallet Balance',$stats['wallet'],'bi-safe2']] as $stat)
      <div class="col-6 col-xl-2"><div class="card h-100 shadow-sm"><div class="card-body"><div class="text-muted small">{{ __($stat[0]) }}</div><div class="fs-4 fw-bold mt-1">{{ is_numeric($stat[1]) && in_array($stat[0], ['Customer Collection','Commission','Payouts','Wallet Balance'], true) ? '৳'.number_format($stat[1],2) : number_format($stat[1]) }}</div><i class="bi {{ $stat[2] }} text-success"></i></div></div></div>
    @endforeach
  </div>
  <div class="row g-3">
    <div class="col-md-4"><a class="card h-100 text-decoration-none shadow-sm" href="{{ route('admin.resellers.index') }}"><div class="card-body"><i class="bi bi-people fs-3 text-success"></i><h5 class="mt-2 mb-1">{{ __('Partner Management') }}</h5><small class="text-muted">{{ __('Create, edit, suspend partners and assign packages/permissions.') }}</small></div></a></div>
    <div class="col-md-4"><a class="card h-100 text-decoration-none shadow-sm" href="{{ route('reseller-cashflow', ['from'=>$from,'to'=>$to]) }}"><div class="card-body"><i class="bi bi-arrow-left-right fs-3 text-primary"></i><h5 class="mt-2 mb-1">{{ __('Cashflow') }}</h5><small class="text-muted">{{ __('Incoming collections and wallet spend in one operational view.') }}</small></div></a></div>
    <div class="col-md-4"><a class="card h-100 text-decoration-none shadow-sm" href="{{ route('reseller-ledger', ['from'=>$from,'to'=>$to]) }}"><div class="card-body"><i class="bi bi-journal-check fs-3 text-info"></i><h5 class="mt-2 mb-1">{{ __('Ledger') }}</h5><small class="text-muted">{{ __('Filter, search and export partner credit/spend history.') }}</small></div></a></div>
  </div>
  <div class="card mt-3 shadow-sm"><div class="card-header fw-semibold"><i class="bi bi-shield-check me-1"></i>{{ __('Existing Reseller Platform') }}</div><div class="card-body small text-muted">{{ __('The Partner Network module reuses the production reseller management, wallet, voucher and purchase-request services already deployed in X-Link Billing.') }}</div></div>
</div>
</x-app-layout>
