<x-app-layout>
<x-slot name="header">Billing &amp; Finance</x-slot>
<div class="row g-3">
<div class="col-md-4"><a class="card h-100 text-decoration-none" href="{{ route('income-summary') }}"><div class="card-body"><h5>Income &amp; Collection</h5><p class="text-muted mb-0">Collection trends, sources and payment analysis.</p></div></a></div>
<div class="col-md-4"><a class="card h-100 text-decoration-none" href="{{ route('ledger-summary') }}"><div class="card-body"><h5>Ledger Summary</h5><p class="text-muted mb-0">Credit, debit and ledger activity.</p></div></a></div>
<div class="col-md-4"><a class="card h-100 text-decoration-none" href="{{ route('extra-charges') }}"><div class="card-body"><h5>Extra Charges</h5><p class="text-muted mb-0">Customer additional charge entries.</p></div></a></div>
<div class="col-md-4"><a class="card h-100 text-decoration-none" href="{{ route('admin.expenses') }}"><div class="card-body"><h5>Expenses</h5><p class="text-muted mb-0">Operational expense management.</p></div></a></div>
<div class="col-md-4"><a class="card h-100 text-decoration-none" href="{{ route('admin.profit-summary') }}"><div class="card-body"><h5>Profit &amp; Loss</h5><p class="text-muted mb-0">Income versus expenses.</p></div></a></div>
<div class="col-md-4"><a class="card h-100 text-decoration-none" href="{{ route('payment-collection') }}"><div class="card-body"><h5>Payment Collection</h5><p class="text-muted mb-0">Collection desk and payment operations.</p></div></a></div>
</div>
</x-app-layout>
