<?php

use App\Http\Controllers\Admin\ProfitSummaryController;
use App\Http\Controllers\Admin\ResellerController;
use App\Livewire\Admin\ExpenseManager;
use App\Livewire\Admin\ActivityLogViewer;
use App\Livewire\Admin\ManagePurchaseRequests;
use App\Livewire\Admin\LoginLogViewer;
use App\Livewire\Admin\ManageReviews;
use App\Livewire\Admin\AdminVoucherList;
use App\Livewire\Admin\SystemLogViewer;
use App\Http\Controllers\CollectionReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MainSiteController;
use App\Http\Controllers\Payment\BkashPaymentController;
use App\Http\Controllers\Payment\NagadPaymentController;
use App\Http\Controllers\Payment\SslCommerzPaymentController;
use App\Http\Controllers\Portal\PortalVoucherController;
use App\Http\Controllers\Reseller\ResellerDashboardController;
use App\Livewire\AddressSetup;
use App\Livewire\Admin\ManageRole;
use App\Livewire\Admin\ManageTickets;
use App\Livewire\Admin\ManageUser;
use App\Livewire\CollectionEdit;
use App\Livewire\CommentSubmit;
use App\Livewire\CustomerList;
use App\Livewire\CustomerSummary;
use App\Livewire\EditCustomer;
use App\Livewire\MainSiteSetup;
use App\Livewire\Mikrotik\BackupManager;
use App\Livewire\Mikrotik\FirewallSetup;
use App\Livewire\Mikrotik\HotspotManager;
use App\Livewire\Mikrotik\InterfaceSetup;
use App\Livewire\Mikrotik\IpSetup;
use App\Livewire\Mikrotik\PppoeSetup;
use App\Livewire\Mikrotik\QueueSetup;
use App\Livewire\Mikrotik\RadiusSetup;
use App\Livewire\Mikrotik\RouterLogViewer;
use App\Livewire\Mikrotik\TrafficMonitor;
use App\Livewire\NetworkMap;
use App\Livewire\HighUsageMonitor;
use App\Livewire\DeviceWatcher;
use App\Livewire\MikrotikLoginMessages;
use App\Livewire\Mikrotik\VpnSetup;
use App\Livewire\Mikrotik\WalledGardenSetup;
use App\Livewire\MikrotikSync;
use App\Livewire\MikrotikClients;
use App\Livewire\NewCustomer;
use App\Livewire\NotificationListAll;
use App\Livewire\PackageListSetup;
use App\Livewire\Payment\Invoice;
use App\Livewire\PaymentCollection;
use App\Livewire\Report\DisReport;
use App\Livewire\Reseller\ResellerCustomerList;
use App\Livewire\Reseller\ResellerPackageManagement;
use App\Livewire\Reseller\ResellerVoucherManagement;
use App\Livewire\Reseller\ResellerWalletManagement;
use App\Livewire\SMSSetup;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Http\Middleware\EnsureBillingPort;
use App\Http\Middleware\EnsurePortalPort;

// Extract domain host from APP_URL for consistent subdomain routing
$baseDomain = parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url');

// Main domain
Route::domain($baseDomain)->group(function () {
    Route::get('/', [MainSiteController::class, 'index'])->name('welcome');
    Route::get('/all-packages', [MainSiteController::class, 'allPackages'])->name('all-packages');
    Route::get('/lang/{locale}', function ($locale) {
        if (in_array($locale, ['en', 'bn'])) {
            session()->put('main_site_locale', $locale);
        }
        return redirect()->back();
    })->name('welcome.lang');

    // Warning / Recharge page for expired users
    Route::get('/warning', function () {
        return view('warning');
    })->name('warning');

    // Public voucher redemption route on main domain
    Route::get('/recharge/voucher', [PortalVoucherController::class, 'showRechargeForm'])->name('welcome.voucher.recharge');
    Route::post('/recharge/voucher', [PortalVoucherController::class, 'redeem'])->name('welcome.voucher.redeem');

    Route::get('/portal', function () {
        $host = request()->getHost();
        if (Str::startsWith($host, 'portal.')) {
            return redirect('/');
        }

        return redirect()->to('http://'.$host.':8082/');
    });

    Route::get('/billing', function () {
        $host = request()->getHost();
        if (Str::startsWith($host, 'billing.')) {
            return redirect('/');
        }

        return redirect()->to('http://'.$host.':8081/');
    });
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'restrict.profile',
])->group(function () use ($baseDomain) {

    // billing domain
    Route::middleware(EnsureBillingPort::class)->group(function () {
        Route::get('/system/db-backup/download/{filename}', function ($filename) {
            if (str_contains($filename, '/') || str_contains($filename, '\\')) {
                abort(403, 'Invalid filename.');
            }
            $path = base_path('backups/'.$filename);
            if (file_exists($path)) {
                return response()->download($path);
            }
            abort(404, 'Backup file not found.');
        })->name('system.db-backup.download');

        Route::redirect('/', '/dashboard');

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resources([
            'collection-report' => CollectionReportController::class,
        ]);
        Route::get('customers/data', [CustomerList::class, 'getData'])->name('customers.data');
        Route::get('customers/{id}/edit', [CustomerList::class, 'edit'])->name('customers.edit');
        Route::get('customers/{id}', [CustomerList::class, 'show'])->name('customers.show');
        Route::patch('customers/{id}', [CustomerList::class, 'update'])->name('customers.update');
        Route::get('customers', CustomerList::class)->name('customers.index');

        Route::get('/new/customers', CustomerList::class)->name('customers-new');
        Route::get('/admin-users', ManageUser::class)->name('admin-users');
        Route::get('/admin-roles', ManageRole::class)->name('admin-roles');
        Route::get('/support-tickets', ManageTickets::class)->name('admin-tickets');
        Route::get('/mikrotik', MikrotikSync::class)->name('mikrotik-sync');
        Route::get('/mikrotik-server', MikrotikSync::class)->name('mikrotik-server');
        Route::get('/mikrotik-server/backup', BackupManager::class)->name('mikrotik-server-backup');
        Route::get('/mikrotik-server/import', MikrotikClients::class)->name('mikrotik-server-import');
        Route::get('/mikrotik-server/bulk-import', CustomerList::class)->name('mikrotik-server-bulk-import');

        // Network Monitoring suite
        Route::get('/network-map', NetworkMap::class)->name('network-map');
        Route::get('/traffic-monitor', TrafficMonitor::class)->name('traffic-monitor');
        Route::get('/high-usage-monitor', HighUsageMonitor::class)->name('high-usage-monitor');
        Route::get('/device-watcher', DeviceWatcher::class)->name('device-watcher');
        Route::get('/mikrotik-login-messages', MikrotikLoginMessages::class)->name('mikrotik-login-messages');

        // Mikrotik Setup Routes
        Route::prefix('mikrotik-setup')->group(function () {
            Route::get('/ip', IpSetup::class)->name('mikrotik-ip-setup');
            Route::get('/pppoe', PppoeSetup::class)->name('mikrotik-pppoe-setup');
            Route::get('/queue', QueueSetup::class)->name('mikrotik-queue-setup');
            Route::get('/firewall', FirewallSetup::class)->name('mikrotik-firewall-setup');
            Route::get('/hotspot', HotspotManager::class)->name('mikrotik-hotspot-setup'); // merged → HotspotManager
            Route::get('/hotspot-manager', HotspotManager::class)->name('mikrotik-hotspot-manager');
            Route::get('/radius', RadiusSetup::class)->name('mikrotik-radius-setup');
            Route::get('/vpn', VpnSetup::class)->name('mikrotik-vpn-setup');
            Route::get('/interface', InterfaceSetup::class)->name('mikrotik-interface-setup');
            Route::get('/traffic', TrafficMonitor::class)->name('mikrotik-traffic-monitor');
            Route::get('/logs', RouterLogViewer::class)->name('mikrotik-log-viewer');
            Route::get('/backup', BackupManager::class)->name('mikrotik-backup-setup');
            Route::get('/walled-garden', WalledGardenSetup::class)->name('mikrotik-walled-garden');
        });

        Route::get('/address', AddressSetup::class)->name('address-setup');
        Route::get('/packages', PackageListSetup::class)->name('package-list-setup');
        Route::get('/sms', SMSSetup::class)->name('sms-setup');
        Route::get('/create-customer', NewCustomer::class)->name('new-customer');

        // payment routes
        Route::get('/payment-collection', PaymentCollection::class)->name('payment-collection');
        Route::get('/payment-collection-edit', CollectionEdit::class)->name('collection-edit');
        Route::get('/payment-invoice', Invoice::class)->name('payment-invoice');

        // all report
        Route::get('/customer-summary', CustomerSummary::class)->name('customer-summary');
        Route::get('/report/dis-report-table', [DisReport::class, 'getData'])->name('dis-report-table');
        Route::get('/report/dis-report', DisReport::class)->name('dis-report');

        // site settings (consolidated)
        Route::get('/site-settings', MainSiteSetup::class)
            ->middleware(DispatchServingFilamentEvent::class)
            ->name('site-settings');

        // main site content management (deprecate name but keeps route if needed or redirection)
        Route::get('/main-site-setup', function () {
            return redirect()->route('site-settings');
        })->name('main-site-setup');

        // X-Link Billing control center with live operational KPIs.
        Route::get('/xlink-billing', function () {
            $customers = \App\Models\CustomersInfo::count();
            $routers = \App\Models\RouterList::count();
            $openTickets = \App\Models\SupportTicket::whereIn('status', ['open','pending','in_progress'])->count();
            $pendingRequests = \App\Models\PackagePurchaseRequest::whereIn('status', ['pending','requested'])->count();
            $watchers = \App\Models\DeviceWatcher::count();
            return view('xlink.index', [
                'kpis' => [
                    ['label'=>'Customers','value'=>$customers,'icon'=>'bi-people'],
                    ['label'=>'Routers','value'=>$routers,'icon'=>'bi-router'],
                    ['label'=>'Open Tickets','value'=>$openTickets,'icon'=>'bi-life-preserver'],
                    ['label'=>'Pending Requests','value'=>$pendingRequests,'icon'=>'bi-hourglass-split'],
                    ['label'=>'Device Watchers','value'=>$watchers,'icon'=>'bi-eye'],
                ],
                'modules' => [
                    ['Mobile Banking','xlink.mobile-banking','bi-phone'],
                    ['Partner Network','xlink.partner-network','bi-diagram-3'],
                    ['Bandwidth Reseller','xlink.bandwidth-reseller','bi-speedometer2'],
                    ['Devices Inventory','xlink.devices-inventory','bi-hdd-network'],
                    ['Stock Inventory','xlink.stock-inventory','bi-box-seam'],
                    ['Communication Center','xlink.communication-center','bi-chat-dots'],
                    ['Support Center','xlink.support-center','bi-headset'],
                    ['Team & Access','xlink.team-access','bi-people'],
                    ['System Settings','xlink.system-settings','bi-gear'],
                    ['Billing Helpline','xlink.billing-helpline','bi-telephone'],
                    ['Profile & Security','xlink.profile-security','bi-shield-lock'],
                    ['Network Map','network-map','bi-diagram-3'],
                    ['Traffic Monitor','traffic-monitor','bi-activity'],
                    ['High Usage Monitor','high-usage-monitor','bi-bar-chart-line'],
                    ['Device Watcher','device-watcher','bi-eye'],
                    ['Logs & Alerts','mikrotik-login-messages','bi-bell'],
                ],
            ]);
        })->name('xlink.index');

        // X-Link Billing functional module hubs — backed by existing production features.
        Route::get('/mobile-banking', fn () => view('xlink.module', [
            'title'=>'Mobile Banking','icon'=>'bi-phone','description'=>'Mobile payment gateways, SMS and collection operations.',
            'stats'=>[
                ['Gateway','Operational'],
                ['bKash',\App\Models\MainSiteData::getValue('payment_bkash_enabled', 0) ? 'Enabled' : 'Disabled'],
                ['Nagad',\App\Models\MainSiteData::getValue('payment_nagad_enabled', 0) ? 'Enabled' : 'Disabled'],
                ['SSLCommerz',\App\Models\MainSiteData::getValue('payment_sslcommerz_enabled', 0) ? 'Enabled' : 'Disabled'],
            ],
            'links'=>[[route('site-settings'),'Payment Gateway Settings','Configure bKash, Nagad and SSLCommerz'],[route('payment-collection'),'Payment Collection','Collection desk'],[route('payment-invoice'),'Invoices','Payment invoices']],
        ]))->name('xlink.mobile-banking');
        Route::get('/partner-network', fn () => view('xlink.module', [
            'title'=>'Partner Network','icon'=>'bi-diagram-3','description'=>'Partner and reseller network management.',
            'stats'=>[
                ['Partners',\App\Models\Reseller::count()],
                ['Active',\App\Models\Reseller::where('status','active')->count()],
                ['Requests',\App\Models\PackagePurchaseRequest::count()],
            ],
            'links'=>[[route('admin.resellers.index'),'Partner Management','Create and manage partners'],[route('admin.purchase-requests'),'Purchase Requests','Review partner requests'],[route('reseller.dashboard'),'Partner Dashboard','Reseller operations']],
        ]))->name('xlink.partner-network');
        Route::get('/bandwidth-reseller', fn () => view('xlink.module', [
            'title'=>'Bandwidth Reseller','icon'=>'bi-speedometer2','description'=>'Reseller packages, wallet, vouchers and customer operations.',
            'stats'=>[
                ['Resellers',\App\Models\Reseller::count()],
                ['Customers',\App\Models\CustomersInfo::whereNotNull('reseller_id')->count()],
                ['Vouchers',\App\Models\Voucher::count()],
            ],
            'links'=>[[route('reseller.dashboard'),'Reseller Dashboard','Operations'],[route('reseller.packages.index'),'Packages','Package management'],[route('reseller.wallet.index'),'Wallet','Balances and transactions'],[route('reseller.vouchers.index'),'Vouchers','Voucher management']],
        ]))->name('xlink.bandwidth-reseller');
        Route::get('/network-inventory', function () {
            $routers = \App\Models\RouterList::orderBy('router_name')->get();
            $totalRouters = $routers->count();
            $onlineRouters = $routers->where('action', 'connected')->count();
            $offlineRouters = max(0, $totalRouters - $onlineRouters);
            $routerAttention = $routers->where('action', '!=', 'connected')->take(5)->map(fn ($router) => (object)[
                'device_type' => 'MikroTik', 'display_name' => $router->router_name, 'ip_address' => $router->ip_address,
                'health_status' => 'down', 'last_latency_ms' => null, 'last_checked_at' => $router->updated_at,
            ]);
            $deviceAttention = \App\Models\NetworkInventoryDevice::query()->where('monitor_enabled', true)
                ->where(function ($q) { $q->whereIn('health_status', ['down','degraded'])->orWhere('status', 'offline'); })
                ->orderByDesc('last_checked_at')->take(10)->get()->map(fn ($device) => (object)[
                    'device_type' => strtoupper(str_replace('-', ' ', $device->type)), 'display_name' => $device->name,
                    'ip_address' => $device->ip_address, 'health_status' => $device->health_status ?: $device->status,
                    'last_latency_ms' => $device->last_latency_ms, 'last_checked_at' => $device->last_checked_at,
                ]);
            $attention = $routerAttention->merge($deviceAttention)->take(8);
            $healthModel = \App\Models\NetworkInventoryHealthCheck::query();
            $health24 = (clone $healthModel)->where('checked_at', '>=', now()->subDay())->get();
            $health7 = (clone $healthModel)->where('checked_at', '>=', now()->subDays(7))->get();
            $uptime = function ($rows) {
                $total = $rows->count();
                $up = $rows->where('status', 'online')->count();
                return $total ? round(($up / $total) * 100, 2) : null;
            };
            $incidentCount = $health7->whereIn('status', ['down','degraded'])->count();
            $avgLatency = $health24->whereNotNull('latency_ms')->avg('latency_ms');

            return view('xlink.network-inventory', [
                'routerTotal' => $totalRouters,
                'routerOnline' => $onlineRouters,
                'oltTotal' => (int) \App\Models\NetworkInventoryDevice::type('olt')->count(),
                'oltOnline' => (int) \App\Models\NetworkInventoryDevice::type('olt')->where('status','online')->count(),
                'switchTotal' => (int) \App\Models\NetworkInventoryDevice::type('switch')->count(),
                'switchOnline' => (int) \App\Models\NetworkInventoryDevice::type('switch')->where('status','online')->count(),
                'apTotal' => (int) \App\Models\NetworkInventoryDevice::type('access-point')->count(),
                'apOnline' => (int) \App\Models\NetworkInventoryDevice::type('access-point')->where('status','online')->count(),
                'attention' => $attention,
                'offlineCount' => $offlineRouters,
                'uptime24' => $uptime($health24),
                'uptime7' => $uptime($health7),
                'incidentCount' => $incidentCount,
                'avgLatency' => $avgLatency !== null ? round($avgLatency, 1) : null,
            ]);
        })->name('network-inventory');

        Route::get('/devices-inventory', fn () => redirect()->route('network-inventory'))
            ->name('xlink.devices-inventory');
        Route::get('/network-inventory/health-history', function () {
            $model = \App\Models\NetworkInventoryHealthCheck::query();
            $health24 = (clone $model)->where('checked_at', '>=', now()->subDay())->get();
            $health7 = (clone $model)->where('checked_at', '>=', now()->subDays(7))->get();
            $uptime = fn ($rows) => $rows->count() ? round(($rows->where('status','online')->count() / $rows->count()) * 100, 2) : null;
            $checks = (clone $model)->with('device')->latest('checked_at')->limit(100)->get();
            return view('xlink.health-history', [
                'checks' => $checks, 'uptime24' => $uptime($health24), 'uptime7' => $uptime($health7),
                'incidentCount' => $health7->whereIn('status', ['down','degraded'])->count(),
                'avgLatency' => $health24->whereNotNull('latency_ms')->count() ? round($health24->whereNotNull('latency_ms')->avg('latency_ms'), 1) : null,
            ]);
        })->name('network-inventory.health-history');

        Route::get('/network-inventory/mikrotik-management', function () {
            $routers = \App\Models\RouterList::orderBy('router_name')->get();
            return view('xlink.mikrotik-management', [
                'routers' => $routers,
                'online' => $routers->where('action', 'connected')->count(),
            ]);
        })->name('network-inventory.mikrotik');


        Route::get('/network-inventory/device/{type}', function (string $type) {
            abort_unless(in_array($type, ['olt','switch','access-point'], true), 404);
            $label = ['olt'=>'OLT','switch'=>'Switch','access-point'=>'Access Point'][$type];
            $query = \App\Models\NetworkInventoryDevice::type($type);
            if ($search = trim((string) request('q', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%")
                      ->orWhere('vendor', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%");
                });
            }
            if (in_array(request('status'), ['online','offline','unknown'], true)) {
                $query->where('status', request('status'));
            }
            $perPage = min(max((int) request('per_page', 10), 10), 50);
            $devices = $query->orderBy('name')->paginate($perPage)->withQueryString();
            return view('xlink.inventory-list', compact('devices','type','label'));
        })->name('network-inventory.devices');

        Route::post('/network-inventory/device/{type}', function (\Illuminate\Http\Request $request, string $type) {
            abort_unless(in_array($type, ['olt','switch','access-point'], true), 404);
            $data = $request->validate([
                'name' => ['required','string','max:120'],
                'ip_address' => ['nullable','ip'],
                'vendor' => ['nullable','string','max:80'],
                'model' => ['nullable','string','max:120'],
                'onu_total' => ['nullable','integer','min:0'],
                'onu_online' => ['nullable','integer','min:0'],
                'rx_power' => ['nullable','numeric','between:-99.99,99.99'],
                'customer_count' => ['nullable','integer','min:0'],
                'status' => ['required','in:online,offline,unknown'],
                'location' => ['nullable','string','max:160'],
                'notes' => ['nullable','string','max:1000'],
                'health_port' => ['nullable','integer','min:1','max:65535'],
                'onu_total' => ['nullable','integer','min:0'],
                'onu_online' => ['nullable','integer','min:0'],
                'rx_power' => ['nullable','numeric'],
                'customer_count' => ['nullable','integer','min:0'],
                'monitor_enabled' => ['nullable','boolean'],
            ]);
            \App\Models\NetworkInventoryDevice::create($data + [
                'type' => $type,
                'monitor_enabled' => (bool) ($data['monitor_enabled'] ?? false),
            ]);
            return back()->with('inventory_message', ucfirst(str_replace('-', ' ', $type)).' device added.');
        })->name('network-inventory.devices.store');

        Route::put('/network-inventory/device/{type}/{device}', function (\Illuminate\Http\Request $request, string $type, \App\Models\NetworkInventoryDevice $device) {
            abort_unless(in_array($type, ['olt','switch','access-point'], true) && $device->type === $type, 404);
            $data = $request->validate([
                'name' => ['required','string','max:120'], 'ip_address' => ['nullable','ip'],
                'vendor' => ['nullable','string','max:80'], 'model' => ['nullable','string','max:120'],
                'status' => ['required','in:online,offline,unknown'], 'location' => ['nullable','string','max:160'],
                'notes' => ['nullable','string','max:1000'],
                'health_port' => ['nullable','integer','min:1','max:65535'],
                'onu_total' => ['nullable','integer','min:0'],
                'onu_online' => ['nullable','integer','min:0'],
                'rx_power' => ['nullable','numeric'],
                'customer_count' => ['nullable','integer','min:0'],
                'monitor_enabled' => ['nullable','boolean'],
            ]);
            $device->update($data + ['monitor_enabled' => (bool) ($data['monitor_enabled'] ?? false)]);
            return back()->with('inventory_message', 'Inventory record updated.');
        })->name('network-inventory.devices.update');

        Route::post('/network-inventory/device/{type}/bulk-status', function (\Illuminate\Http\Request $request, string $type) {
            abort_unless(in_array($type, ['olt','switch','access-point'], true), 404);
            $data = $request->validate(['device_ids'=>['required','array','min:1'],'device_ids.*'=>['integer'],'status'=>['required','in:online,offline,unknown']]);
            \App\Models\NetworkInventoryDevice::type($type)->whereIn('id',$data['device_ids'])->update(['status'=>$data['status']]);
            return back()->with('inventory_message', count($data['device_ids']).' device status records updated.');
        })->name('network-inventory.devices.bulk-status');

        Route::delete('/network-inventory/device/{type}/{device}', function (string $type, \App\Models\NetworkInventoryDevice $device) {
            abort_unless(in_array($type, ['olt','switch','access-point'], true) && $device->type === $type, 404);
            $device->delete();
            return back()->with('inventory_message', 'Inventory record deleted.');
        })->name('network-inventory.devices.destroy');

        Route::get('/network-inventory/olt-management', function () {
            $devices = \App\Models\NetworkInventoryDevice::type('olt')->orderBy('name')->get();
            return view('xlink.olts', ['devices' => $devices]);
        })->name('network-inventory.olt');

        Route::get('/olts', fn () => redirect()->route('network-inventory.olt'))->name('olts');

        Route::get('/network-inventory/switch-management', fn () => view('xlink.device-management', [
            'title' => 'Switch Management', 'icon' => 'bi-ethernet',
            'description' => 'Switch inventory and port health management.', 'status' => 'Integration ready',
            'stats' => [['Total Switches',(int) config('app.network_inventory_switch_total',0)],['Online',(int) config('app.network_inventory_switch_online',0)],['Offline',max(0,(int) config('app.network_inventory_switch_total',0)-(int) config('app.network_inventory_switch_online',0))]],
            'links' => [['network-inventory','Network Inventory','Back to device overview'],['network-map','Topology','View network topology']],
        ]))->name('network-inventory.switches');

        Route::get('/network-inventory/access-point-management', fn () => view('xlink.device-management', [
            'title' => 'Access Point Management', 'icon' => 'bi-wifi',
            'description' => 'Wireless access point inventory and availability management.', 'status' => 'Integration ready',
            'stats' => [['Total APs',(int) config('app.network_inventory_ap_total',0)],['Online',(int) config('app.network_inventory_ap_online',0)],['Offline',max(0,(int) config('app.network_inventory_ap_total',0)-(int) config('app.network_inventory_ap_online',0))]],
            'links' => [['network-inventory','Network Inventory','Back to device overview'],['device-watcher','Device Watcher','Monitor reachability']],
        ]))->name('network-inventory.access-points');

        Route::get('/stock-inventory', function () {
            $requests = \App\Models\PackagePurchaseRequest::query();
            $pending = (clone $requests)->whereIn('status',['pending','requested'])->count();
            $total = (clone $requests)->count();
            $packages = \App\Models\PackageList::count();
            return view('xlink.module', [
                'title'=>'Stock Inventory','icon'=>'bi-box-seam','description'=>'Package catalog, stock requests and procurement workflow.',
                'stats'=>[['label'=>'Packages','value'=>$packages],['label'=>'Pending Requests','value'=>$pending],['label'=>'Total Requests','value'=>$total]],
                'links'=>[
                    ['url'=>route('package-list-setup'),'label'=>'Package Catalog','hint'=>'Manage service packages'],
                    ['url'=>route('admin.purchase-requests'),'label'=>'Purchase Requests','hint'=>'Review and process requests'],
                    ['url'=>route('admin.vouchers'),'label'=>'Voucher Inventory','hint'=>'Voucher administration'],
                ],
            ]);
        })->name('xlink.stock-inventory');
        Route::get('/communication-center', function () {
            $templates = \App\Models\SmsTemplate::count();
            $notifications = \App\Models\NotificationLogs::count();
            return view('xlink.module', [
                'title'=>'Communication Center','icon'=>'bi-chat-dots','description'=>'Central SMS, notifications and customer communication tools.',
                'stats'=>[['label'=>'SMS Templates','value'=>$templates],['label'=>'Notifications','value'=>$notifications],['label'=>'Bridge','value'=>'Ready']],
                'links'=>[
                    ['url'=>route('sms-setup'),'label'=>'SMS Setup','hint'=>'Gateway and messaging configuration'],
                    ['url'=>route('sms-bridge.index'),'label'=>'SMS Bridge','hint'=>'Bridge operations'],
                    ['url'=>route('notifications'),'label'=>'Notifications','hint'=>'Notification center'],
                    ['url'=>route('admin-tickets'),'label'=>'Support Tickets','hint'=>'Customer communication'],
                ],
            ]);
        })->name('xlink.communication-center');
        Route::get('/support-center', function () {
            $tickets = \App\Models\SupportTicket::query();
            $open = (clone $tickets)->whereIn('status',['open','pending','in_progress'])->count();
            $total = (clone $tickets)->count();
            return view('xlink.module', [
                'title'=>'Support Center','icon'=>'bi-headset','description'=>'Customer support desk with live ticket visibility and operational logs.',
                'stats'=>[['label'=>'Open Tickets','value'=>$open],['label'=>'Total Tickets','value'=>$total],['label'=>'Status','value'=>'Online']],
                'links'=>[
                    ['url'=>route('admin-tickets'),'label'=>'Support Tickets','hint'=>'Manage customer issues'],
                    ['url'=>route('admin.activity-logs'),'label'=>'Activity Logs','hint'=>'Operational history'],
                    ['url'=>route('admin.login-logs'),'label'=>'Login Logs','hint'=>'Authentication activity'],
                ],
            ]);
        })->name('xlink.support-center');
        Route::get('/team-access', function () {
            $users = \App\Models\User::count();
            $roles = class_exists(\Spatie\Permission\Models\Role::class) ? \Spatie\Permission\Models\Role::count() : 0;
            return view('xlink.module', [
                'title'=>'Team & Access','icon'=>'bi-people','description'=>'Users, roles and access administration with protected admin controls.',
                'stats'=>[
                    ['label'=>'Users','value'=>$users],
                    ['label'=>'Roles','value'=>$roles],
                    ['label'=>'Auth','value'=>'Protected'],
                ],
                'links'=>[
                    ['url'=>route('admin-users'),'label'=>'Manage Users','hint'=>'Create, edit and review users'],
                    ['url'=>route('admin-roles'),'label'=>'Manage Roles','hint'=>'Roles and permissions'],
                    ['url'=>route('profile.show'),'label'=>'My Profile','hint'=>'Account settings'],
                    ['url'=>route('admin.login-logs'),'label'=>'Login Logs','hint'=>'Authentication history'],
                ],
            ]);
        })->name('xlink.team-access');
        Route::get('/system-settings', function () {
            $debug = config('app.debug') ? 'ON' : 'OFF';
            return view('xlink.module', [
                'title'=>'System Settings','icon'=>'bi-gear','description'=>'Central application, branding, MikroTik and messaging configuration.',
                'stats'=>[
                    ['label'=>'Environment','value'=>app()->environment()],
                    ['label'=>'Debug','value'=>$debug],
                    ['label'=>'Status','value'=>'Online'],
                ],
                'links'=>[
                    ['url'=>route('site-settings'),'label'=>'Site Settings','hint'=>'Branding and site configuration'],
                    ['url'=>route('mikrotik-sync'),'label'=>'MikroTik Setup','hint'=>'Router integration and synchronization'],
                    ['url'=>route('sms-setup'),'label'=>'SMS Setup','hint'=>'Gateway and messaging configuration'],
                    ['url'=>route('main-site-setup'),'label'=>'Main Site Setup','hint'=>'Website content configuration'],
                ],
            ]);
        })->name('xlink.system-settings');
        Route::get('/billing-helpline', function () {
            $tickets = \App\Models\SupportTicket::query();
            $open = (clone $tickets)->whereIn('status',['open','pending','in_progress'])->count();
            $total = (clone $tickets)->count();
            return view('xlink.module', [
                'title'=>'Billing Helpline','icon'=>'bi-telephone','description'=>'Billing support, collections and issue escalation desk.',
                'stats'=>[
                    ['label'=>'Open Tickets','value'=>$open],
                    ['label'=>'Total Tickets','value'=>$total],
                    ['label'=>'Billing','value'=>'Online'],
                ],
                'links'=>[
                    ['url'=>route('admin-tickets'),'label'=>'Support Tickets','hint'=>'Handle billing/customer issues'],
                    ['url'=>route('payment-collection'),'label'=>'Payment Collection','hint'=>'Collection desk'],
                    ['url'=>route('collection-report.index'),'label'=>'Collection Report','hint'=>'Billing reporting'],
                    ['url'=>route('customer-summary'),'label'=>'Customer Summary','hint'=>'Customer billing history'],
                ],
            ]);
        })->name('xlink.billing-helpline');
        Route::get('/profile-security', function () {
            return view('xlink.module', [
                'title'=>'Profile & Security','icon'=>'bi-shield-lock','description'=>'Profile, authentication, sessions and access-security controls.',
                'stats'=>[
                    ['label'=>'Authentication','value'=>'Protected'],
                    ['label'=>'2FA','value'=>'Available'],
                    ['label'=>'Debug','value'=>config('app.debug') ? 'ON' : 'OFF'],
                ],
                'links'=>[
                    ['url'=>route('profile.show'),'label'=>'My Profile','hint'=>'Profile and personal settings'],
                    ['url'=>route('two-factor.login'),'label'=>'Two-Factor Authentication','hint'=>'Manage account 2FA'],
                    ['url'=>route('xlink.team-access'),'label'=>'Team & Access','hint'=>'Users and roles'],
                    ['url'=>route('admin.login-logs'),'label'=>'Login Logs','hint'=>'Authentication events'],
                ],
            ]);
        })->name('xlink.profile-security');

        Route::get('/all-notifications', NotificationListAll::class)->name('notifications');
        // Route::get('/edit-customer', EditCustomer::class);
        // Route::get('/customers', CustomerList::class);

        // X-Link Billing parity aliases — reuse existing production modules.
        Route::get('/mobile-banking', fn () => redirect()->route('sms-setup'))->name('xlink.mobile-banking');
        Route::get('/partner-network', fn () => redirect()->route('admin.resellers.index'))->name('xlink.partner-network');
        Route::get('/bandwidth-reseller', fn () => redirect()->route('reseller.dashboard'))->name('xlink.bandwidth-reseller');
        Route::get('/devices-inventory', fn () => redirect()->route('mikrotik-server'))->name('xlink.devices-inventory');
        Route::get('/stock-inventory', fn () => redirect()->route('admin.purchase-requests'))->name('xlink.stock-inventory');
        Route::get('/communication-center', fn () => redirect()->route('sms-bridge.index'))->name('xlink.communication-center');
        Route::get('/support-center', fn () => redirect()->route('admin-tickets'))->name('xlink.support-center');
        Route::get('/team-access', fn () => redirect()->route('admin-users'))->name('xlink.team-access');
        Route::get('/system-settings', fn () => redirect()->route('site-settings'))->name('xlink.system-settings');
        Route::get('/billing-helpline', fn () => redirect()->route('admin-tickets'))->name('xlink.billing-helpline');
        Route::get('/profile-security', fn () => redirect()->route('profile.show'))->name('xlink.profile-security');

        Route::get('/all-notifications', NotificationListAll::class)->name('notifications');
        // Route::get('/edit-customer', EditCustomer::class);
        // Route::get('/customers', CustomerList::class);

        Route::get('import-form', [ImportController::class, 'importForm'])->name('import.form');
        Route::post('collection-form', [ImportController::class, 'collectionForm'])->name('collection.form');
        Route::post('monthly-bill-form', [ImportController::class, 'monthlyBillForm'])->name('monthly.bill.form');
        // Route::post('import-preview', [ImportController::class, 'importView'])->name('import.preview');
        Route::post('import-store', [ImportController::class, 'import'])->name('import');

        // Route::get('/user/profile', [UserProfileController::class, 'index'])->name('user.profile');
        // Route::post('/user/profile/upload', [UserProfileController::class, 'uploadFile'])->name('user.profile.upload');
        // Route::get('/user/profile/update', [UserProfileController::class, 'update'])->name('user.profile.update');
        // Route::get('/user/password/update', [UserProfileController::class, 'updatePassword'])->name('user.password.update');

        Route::get('/submit-comment', CommentSubmit::class)->name('submit.comment');

        // Admin Reseller Management
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::resource('resellers', ResellerController::class)->except(['show']);
            Route::post('resellers/{reseller}/adjust-balance', [ResellerController::class, 'adjustBalance'])->name('resellers.adjust-balance');
            Route::get('resellers/{reseller}/transactions', [ResellerController::class, 'getTransactionsJson'])->name('resellers.transactions');

            // Expense tracking
            Route::get('expenses', ExpenseManager::class)->name('expenses');

            // Profit & Loss summary
            Route::get('profit-summary', [ProfitSummaryController::class, 'index'])->name('profit-summary');

            // Activity Log Viewer
            Route::get('activity-logs', ActivityLogViewer::class)->name('activity-logs');

            // System Logs Viewer
            Route::get('system-logs', SystemLogViewer::class)->name('system-logs');

            // Login Logs Viewer
            Route::get('login-logs', LoginLogViewer::class)->name('login-logs');

            // Customer Reviews Management
            Route::get('reviews', ManageReviews::class)->name('reviews');

            // Reseller Vouchers Audit
            Route::get('vouchers', AdminVoucherList::class)->name('vouchers');

            // Package purchase requests
            Route::get('purchase-requests', ManagePurchaseRequests::class)->name('purchase-requests');
        });

        // Reseller Portal Routes
        Route::prefix('reseller')->middleware(['reseller'])->name('reseller.')->group(function () {
            Route::get('/dashboard', [ResellerDashboardController::class, 'index'])->name('dashboard');
            Route::get('customers/data', [ResellerCustomerList::class, 'getData'])->name('customers.data');
            Route::get('customers', ResellerCustomerList::class)->name('customers.index');
            Route::get('customers/create', NewCustomer::class)->name('customers.create');
            Route::get('customers/{customerId}/edit', EditCustomer::class)->name('customers.edit');

            Route::get('packages', ResellerPackageManagement::class)->name('packages.index');

            // Vouchers & Wallet — always accessible to all resellers
            Route::get('vouchers', ResellerVoucherManagement::class)->name('vouchers.index');
            Route::get('wallet', ResellerWalletManagement::class)->name('wallet.index');
        });
    });
});


// portal domain routes
Route::middleware(EnsurePortalPort::class)->group(function () {
    // Authenticated portal payment initiation routes
    Route::middleware(['auth:ppp'])->group(function () {
        Route::get('/payment/bkash/initiate', [BkashPaymentController::class, 'initiate'])->name('payment.bkash.initiate');
        Route::get('/payment/nagad/initiate', [NagadPaymentController::class, 'initiate'])->name('payment.nagad.initiate');
        Route::get('/payment/sslcommerz/initiate', [SslCommerzPaymentController::class, 'initiate'])->name('payment.sslcommerz.initiate');
    });

    // Public payment callback routes (Gateways redirect here, CSRF is disabled for POSTs)
    Route::any('/payment/bkash/callback', [BkashPaymentController::class, 'callback'])->name('payment.bkash.callback');
    Route::any('/payment/nagad/callback', [NagadPaymentController::class, 'callback'])->name('payment.nagad.callback');
    Route::any('/payment/sslcommerz/callback', [SslCommerzPaymentController::class, 'callback'])->name('payment.sslcommerz.callback');
    Route::post('/payment/mock/submit', [BkashPaymentController::class, 'mockSubmit'])->name('payment.mock.submit');

    // Public voucher redemption route
    Route::get('/recharge/voucher', [PortalVoucherController::class, 'showRechargeForm'])->name('portal.voucher.recharge');
    Route::post('/recharge/voucher', [PortalVoucherController::class, 'redeem'])->name('portal.voucher.redeem');
});

// Final fallback: redirect unknown external hosts/IP requests without shadowing application routes.
Route::any('{any}', function () use ($baseDomain) {
    $host = request()->getHost();
    $allowedHosts = [$baseDomain, 'bill.xlinkbd.net', 'portal.'.$baseDomain, 'billing.'.$baseDomain];

    if (in_array($host, $allowedHosts, true)) {
        abort(404);
    }

    return redirect()->away(config('app.url') . '/warning');
})->where('any', '.*');
