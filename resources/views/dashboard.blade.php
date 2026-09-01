<x-app-layout>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('xlink-dashboard/dashboard-presets.css') }}">
        <link rel="stylesheet" href="{{ asset('xlink-dashboard/dashboard-live.css') }}">
        <link rel="stylesheet" href="{{ asset('xlink-dashboard/layout-fixes.css') }}">
        <link rel="stylesheet" href="{{ asset('xlink-dashboard/report-ux.css') }}">
        <link rel="stylesheet" href="{{ asset('xlink-dashboard/ui-polish-20260826.css') }}">
        <link rel="stylesheet" href="{{ asset('xlink-dashboard/sidebar-presets.css') }}">
    @endpush

    <x-slot name="header">{{ __('Dashboard') }}</x-slot>

    <div class="dashboard-card-deck">
        @php
            $cards = [
                ['tone'=>'green','icon'=>'bi-person-check-fill','label'=>'Active Customers','value'=>number_format($activeCustomerTotal ?? 0),'sub'=>'Company '.number_format($activeCompany ?? 0).' • Reseller '.number_format($activeReseller ?? 0)],
                ['tone'=>'green','icon'=>'bi-wifi','label'=>'Online Now','value'=>number_format($onlineNow ?? 0),'sub'=>'Live PPPoE sessions'],
                ['tone'=>'red','icon'=>'bi-calendar-x-fill','label'=>'Expired','value'=>number_format($expired ?? 0),'sub'=>'Billing expiry reached'],
                ['tone'=>'amber','icon'=>'bi-shield-lock-fill','label'=>'Locked / Disabled','value'=>number_format($lockedDisabled ?? 0),'sub'=>'Disabled or temporary'],
                ['tone'=>'purple','icon'=>'bi-calendar2-week-fill','label'=>'This Month Collection','value'=>number_format($monthCollection ?? 0,2).' ৳','sub'=>'PPPoE '.number_format($monthCollectionPppoe ?? 0,2).' ৳ • Hotspot '.number_format($monthCollectionHotspot ?? 0,2).' ৳'],
                ['tone'=>'rose','icon'=>'bi-cash-coin','label'=>'Running Due','value'=>number_format($runningDue ?? 0,2).' ৳','sub'=>'Active customer outstanding'],
                ['tone'=>'teal','icon'=>'bi-wallet2','label'=>'Today Collection','value'=>number_format($todayCollection ?? 0,2).' ৳','sub'=>'PPPoE '.number_format($todayPppoe ?? 0,2).' ৳ • Hotspot '.number_format($todayHotspot ?? 0,2).' ৳'],
                ['tone'=>'cyan','icon'=>'bi-graph-up-arrow','label'=>'Weekly Collection','value'=>number_format($weekCollection ?? 0,2).' ৳','sub'=>'Last 7 days • PPPoE + Hotspot'],
                ['tone'=>'indigo','icon'=>'bi-shop-window','label'=>'Reseller Due','value'=>number_format($resellerDue ?? 0,2).' ৳','sub'=>'Outstanding partner balance'],
                ['tone'=>'slate','icon'=>'bi-calendar2-check-fill','label'=>'Today Attendance','value'=>number_format($attendanceToday ?? 0).' / '.number_format($attendanceTotal ?? 0),'sub'=>'Attendance module'],
                ['tone'=>'orange','icon'=>'bi-phone-vibrate-fill','label'=>'MFS Collection','value'=>number_format($mfsCollection ?? 0,2).' ৳','sub'=>'Digital + SMS banking (month)'],
                ['tone'=>'emerald','icon'=>'bi-router-fill','label'=>'Router Health','value'=>number_format($deviceStats['routers_online'] ?? 0).' / '.number_format($deviceStats['routers_total'] ?? 0),'sub'=>'Online routers / active routers'],
                ['tone'=>'purple','icon'=>'bi-wifi','label'=>'Hotspot Customers','value'=>number_format((int)($hotspotCustomers ?? 0)),'sub'=>'Unique hotspot users this month'],
                ['tone'=>'amber','icon'=>'bi-ticket-perforated','label'=>'Hotspot Card Stock','value'=>number_format(class_exists('App\\Models\\HotspotVoucher') ? \App\Models\HotspotVoucher::whereIn('status',['unused','active'])->count() : 0),'sub'=>'Unused + Active'],
                ['tone'=>'indigo','icon'=>'bi-graph-up','label'=>'Hotspot Monthly','value'=>number_format((float)($monthCollectionHotspot ?? 0),2).' ৳','sub'=>'Hotspot collection this month'],
                ['tone'=>'emerald','icon'=>'bi-broadcast-pin','label'=>'Access Point Health','value'=>number_format($deviceStats['aps_online'] ?? 0).' / '.number_format($deviceStats['aps_total'] ?? 0),'sub'=>'Online AP / total AP'],
            ];
        @endphp
        @foreach($cards as $card)
            <a class="dashboard-card tone-{{ $card['tone'] }}" href="#">
                <span class="dashboard-card-icon"><i class="bi {{ $card['icon'] }}"></i></span>
                <span class="dashboard-card-copy"><span class="dashboard-card-label">{{ __($card['label']) }}</span><strong class="dashboard-card-value">{{ $card['value'] }}</strong><small class="dashboard-card-sub">{{ __($card['sub']) }}</small></span>
                <i class="bi bi-arrow-up-right dashboard-card-open"></i>
            </a>
        @endforeach
    </div>

    <div class="dashboard-masonry-grid mt-3">
        <section class="dashboard-panel panel-collection">
            <header><div><span>{{ __('Collection Trend') }}</span><h3>{{ __('Last 7 Days Collection') }}</h3></div><a href="{{ route('income-summary') }}">{{ __('Full report') }} <i class="bi bi-arrow-right"></i></a></header>
            <div class="dashboard-chart-shell"><canvas id="collectionChart"></canvas></div>
        </section>

        <section class="dashboard-panel panel-recent">
            <header><div><span>{{ __('Ledger Activity') }}</span><h3>{{ __('Recent Transactions') }}</h3></div></header>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>{{ __('Customer / Source') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead><tbody>
                @forelse($recentTransactions as $tx)
                    <tr><td><div class="fw-semibold">{{ optional($tx->customer)->customer_name ?? $tx->customer_collection_unique_id }}</div><div class="small text-secondary">{{ $tx->collection_date }}</div></td><td><span class="badge text-bg-success">{{ $tx->payment_type ?? 'collection' }}</span></td><td class="text-end fw-bold">{{ number_format((float)$tx->collection_amount,2) }} ৳</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-secondary py-4">{{ __('No recent transaction found.') }}</td></tr>
                @endforelse
            </tbody></table></div>
        </section>

        <section class="dashboard-panel panel-customers">
            <header><div><span>{{ __('Customer Accounts') }}</span><h3>{{ __('Customer Account Analysis') }}</h3></div></header>
            <div class="p-3"><div class="row g-2">
                @foreach([['Total',$customersData['total'] ?? 0],['Active',$customersData['active'] ?? 0],['Pending',$customersData['pending'] ?? 0],['Free',$customersData['free'] ?? 0],['Temporary Disable',$customersData['temporary_disable'] ?? 0],['Inactive',$customersData['inactive'] ?? 0]] as $item)
                    <div class="col-6"><div class="p-2 rounded-3 bg-body-tertiary"><div class="small text-secondary">{{ __($item[0]) }}</div><strong>{{ number_format($item[1]) }}</strong></div></div>
                @endforeach
            </div></div>
        </section>

        <section class="dashboard-panel panel-revenue">
            <header><div><span>{{ __('Income Mix') }}</span><h3>{{ __('Monthly Collection Analysis') }}</h3></div></header>
            <div class="dashboard-chart-shell"><canvas id="revenueMixChart"></canvas></div>
            <div class="revenue-summary"><span><i class="broadband"></i>{{ __('Broadband') }} <strong>{{ number_format((float)$monthCollection,2) }} ৳</strong></span><span><i class="hotspot"></i>{{ __('Hotspot') }} <strong>{{ number_format((float)($billInformationData['hotspot_today'] ?? 0),2) }} ৳</strong></span></div>
        </section>

        <section class="dashboard-panel">
            <header><div><span>{{ __('Support') }}</span><h3>{{ __('Ticket Overview') }}</h3></div></header>
            <div class="p-3"><div class="row g-2"><div class="col-6"><div class="p-3 rounded-3 bg-body-tertiary"><div class="small text-secondary">{{ __('Open') }}</div><strong>0</strong></div></div><div class="col-6"><div class="p-3 rounded-3 bg-body-tertiary"><div class="small text-secondary">{{ __('Pending') }}</div><strong>0</strong></div></div><div class="col-6"><div class="p-3 rounded-3 bg-body-tertiary"><div class="small text-secondary">{{ __('Closed') }}</div><strong>0</strong></div></div><div class="col-6"><div class="p-3 rounded-3 bg-body-tertiary"><div class="small text-secondary">{{ __('Today') }}</div><strong>0</strong></div></div></div></div>
        </section>

        <section class="dashboard-panel">
            <header><div><span>{{ __('Connections') }}</span><h3>{{ __('New Connection Graph') }}</h3></div></header>
            <div class="dashboard-chart-shell"><canvas id="newConnectionsChart"></canvas></div>
        </section>
    </div>

    @push('scripts')
        <script src="{{ asset('xlink-dashboard/chart.umd.min.js.download') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const css = getComputedStyle(document.documentElement);
                const grid = document.querySelector('.dashboard-card-deck');
                if (grid) [...grid.querySelectorAll('.dashboard-card')].forEach((el, i) => el.style.animationDelay = `${i * 20}ms`);
                const chartCfg = {responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}};
                const last7 = @json(collect($results)->values());
                const labels = last7.map((_,i)=>`D${i+1}`);
                const totals = last7.map(r => Math.max(0, Number(r.income_current_year || 0)));
                new Chart(document.getElementById('collectionChart'), {type:'line', data:{labels,datasets:[{data:totals,tension:.35,fill:true,borderWidth:2}]}, options:{...chartCfg,scales:{y:{beginAtZero:true}}}});
                new Chart(document.getElementById('revenueMixChart'), {type:'doughnut', data:{labels:['Broadband','Hotspot'],datasets:[{data:[Number(@json($monthCollection)),Number(@json($billInformationData['hotspot_today'] ?? 0))]}]}, options:{...chartCfg,cutout:'68%'}});
                const connectionData = @json($newConnections);
                const monthLabels = Object.keys(connectionData);
                const monthValues = monthLabels.map(k => Number(connectionData[k].total || 0));
                new Chart(document.getElementById('newConnectionsChart'), {type:'bar', data:{labels:monthLabels,datasets:[{data:monthValues,borderRadius:6}]}, options:{...chartCfg,scales:{y:{beginAtZero:true}}}});
            });
        </script>
    @endpush
</x-app-layout>
