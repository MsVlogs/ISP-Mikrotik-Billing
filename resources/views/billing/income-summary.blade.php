<x-app-layout>
<x-slot name="header">Income &amp; Collection</x-slot>
<form class="row g-2 mb-3" method="get">
<div class="col-md-3"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="{{ $from }}"></div>
<div class="col-md-3"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="{{ $to }}"></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Apply</button></div>
</form>
<div class="row g-3 mb-3"><div class="col-md-4"><div class="card"><div class="card-body"><small>Selected Income</small><h3>৳{{ number_format($rows->sum('collection_amount'),2) }}</h3></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><small>Own Customer Income</small><h3>৳{{ number_format($own,2) }}</h3></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><small>Reseller Income</small><h3>৳{{ number_format($reseller,2) }}</h3></div></div></div></div>
<div class="card"><div class="card-body"><h5>Collection Transactions</h5><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Date</th><th>Customer</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th></tr></thead><tbody>@forelse($rows as $r)<tr><td>{{ optional($r->collection_date)->format('Y-m-d') ?? $r->collection_date }}</td><td>{{ optional($r->customer)->customer_name ?? $r->customer_collection_unique_id }}</td><td>{{ $r->payment_method ?: $r->payment_type ?: '—' }}</td><td>{{ $r->transaction_id ?: $r->invoice_no ?: '—' }}</td><td class="text-end">৳{{ number_format((float)$r->collection_amount,2) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No income transaction found.</td></tr>@endforelse</tbody></table></div></div></div>
</x-app-layout>
