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
        });

        // Sweet Billing control center with live operational KPIs.
        Route::get('/sweet-billing', function () {
            $customers = \App\Models\CustomersInfo::count();
            $routers = \App\Models\RouterList::count();
            $openTickets = \App\Models\SupportTicket::whereIn('status', ['open','pending','in_progress'])->count();
            $pendingRequests = \App\Models\PackagePurchaseRequest::whereIn('status', ['pending','requested'])->count();
            $watchers = \App\Models\DeviceWatcher::count();
            return view('sweet.index', [
                'kpis' => [
                    ['label'=>'Customers','value'=>$customers,'icon'=>'bi-people'],
                    ['label'=>'Routers','value'=>$routers,'icon'=>'bi-router'],
                    ['label'=>'Open Tickets','value'=>$openTickets,'icon'=>'bi-life-preserver'],
                    ['label'=>'Pending Requests','value'=>$pendingRequests,'icon'=>'bi-hourglass-split'],
                    ['label'=>'Device Watchers','value'=>$watchers,'icon'=>'bi-eye'],
                ],
                'modules' => [
                    ['Mobile Banking','sweet.mobile-banking','bi-phone'],
                    ['Partner Network','sweet.partner-network','bi-diagram-3'],
                    ['Bandwidth Reseller','sweet.bandwidth-reseller','bi-speedometer2'],
                    ['Devices Inventory','sweet.devices-inventory','bi-hdd-network'],
                    ['Stock Inventory','sweet.stock-inventory','bi-box-seam'],
                    ['Communication Center','sweet.communication-center','bi-chat-dots'],
                    ['Support Center','sweet.support-center','bi-headset'],
                    ['Team & Access','sweet.team-access','bi-people'],
                    ['System Settings','sweet.system-settings','bi-gear'],
                    ['Billing Helpline','sweet.billing-helpline','bi-telephone'],
                    ['Profile & Security','sweet.profile-security','bi-shield-lock'],
                    ['Network Map','network-map','bi-diagram-3'],
                    ['Traffic Monitor','traffic-monitor','bi-activity'],
                    ['High Usage Monitor','high-usage-monitor','bi-bar-chart-line'],
                    ['Device Watcher','device-watcher','bi-eye'],
                    ['Logs & Alerts','mikrotik-login-messages','bi-bell'],
                ],
            ]);
        })->name('sweet.index');

        // Sweet Billing functional module hubs — backed by existing production features.
        Route::get('/mobile-banking', fn () => view('sweet.module', [
            'title'=>'Mobile Banking','icon'=>'bi-phone','description'=>'Mobile payment gateways, SMS and collection operations.',
            'stats'=>[
                ['Gateway','Operational'],
                ['bKash',\App\Models\MainSiteData::getValue('payment_bkash_enabled', 0) ? 'Enabled' : 'Disabled'],
                ['Nagad',\App\Models\MainSiteData::getValue('payment_nagad_enabled', 0) ? 'Enabled' : 'Disabled'],
                ['SSLCommerz',\App\Models\MainSiteData::getValue('payment_sslcommerz_enabled', 0) ? 'Enabled' : 'Disabled'],
            ],
            'links'=>[[route('site-settings'),'Payment Gateway Settings','Configure bKash, Nagad and SSLCommerz'],[route('payment-collection'),'Payment Collection','Collection desk'],[route('payment-invoice'),'Invoices','Payment invoices']],
        ]))->name('sweet.mobile-banking');
        Route::get('/partner-network', fn () => view('sweet.module', [
            'title'=>'Partner Network','icon'=>'bi-diagram-3','description'=>'Partner and reseller network management.',
            'stats'=>[
                ['Partners',\App\Models\Reseller::count()],
                ['Active',\App\Models\Reseller::where('status','active')->count()],
                ['Requests',\App\Models\PackagePurchaseRequest::count()],
            ],
            'links'=>[[route('admin.resellers.index'),'Partner Management','Create and manage partners'],[route('admin.purchase-requests'),'Purchase Requests','Review partner requests'],[route('reseller.dashboard'),'Partner Dashboard','Reseller operations']],
        ]))->name('sweet.partner-network');
        Route::get('/bandwidth-reseller', fn () => view('sweet.module', [
            'title'=>'Bandwidth Reseller','icon'=>'bi-speedometer2','description'=>'Reseller packages, wallet, vouchers and customer operations.',
            'stats'=>[
                ['Resellers',\App\Models\Reseller::count()],
                ['Customers',\App\Models\CustomersInfo::whereNotNull('reseller_id')->count()],
                ['Vouchers',\App\Models\Voucher::count()],
            ],
            'links'=>[[route('reseller.dashboard'),'Reseller Dashboard','Operations'],[route('reseller.packages.index'),'Packages','Package management'],[route('reseller.wallet.index'),'Wallet','Balances and transactions'],[route('reseller.vouchers.index'),'Vouchers','Voucher management']],
        ]))->name('sweet.bandwidth-reseller');
        Route::get('/network-inventory', function () {
            $routers = \App\Models\RouterList::orderBy('router_name')->get();
            $totalRouters = $routers->count();
            $onlineRouters = $routers->where('action', 'connected')->count();
            $offlineRouters = max(0, $totalRouters - $onlineRouters);
            $attention = $routers->where('action', '!=', 'connected')->take(5);

            return view('sweet.network-inventory', [
                'routerTotal' => $totalRouters,
                'routerOnline' => $onlineRouters,
                'oltTotal' => (int) config('app.network_inventory_olt_total', 0),
                'oltOnline' => (int) config('app.network_inventory_olt_online', 0),
                'switchTotal' => (int) config('app.network_inventory_switch_total', 0),
                'switchOnline' => (int) config('app.network_inventory_switch_online', 0),
                'apTotal' => (int) config('app.network_inventory_ap_total', 0),
                'apOnline' => (int) config('app.network_inventory_ap_online', 0),
                'attention' => $attention,
                'offlineCount' => $offlineRouters,
            ]);
        })->name('network-inventory');

        Route::get('/devices-inventory', fn () => redirect()->route('network-inventory'))
            ->name('sweet.devices-inventory');
        Route::get('/network-inventory/mikrotik-management', function () {
            $routers = \App\Models\RouterList::orderBy('router_name')->get();
            return view('sweet.mikrotik-management', [
                'routers' => $routers,
                'online' => $routers->where('action', 'connected')->count(),
            ]);
        })->name('network-inventory.mikrotik');


        Route::get('/network-inventory/olt-management', fn () => view('sweet.device-management', [
            'title' => 'OLT Management', 'icon' => 'bi-diagram-3',
            'description' => 'Optical line terminal inventory and health management.', 'status' => 'Integration ready',
            'stats' => [['Total OLTs',(int) config('app.network_inventory_olt_total',0)],['Online',(int) config('app.network_inventory_olt_online',0)],['Offline',max(0,(int) config('app.network_inventory_olt_total',0)-(int) config('app.network_inventory_olt_online',0))]],
            'links' => [['network-inventory','Network Inventory','Back to device overview'],['main-site-setup','Inventory Settings','Configure inventory source']],
        ]))->name('network-inventory.olt');

        Route::get('/network-inventory/switch-management', fn () => view('sweet.device-management', [
            'title' => 'Switch Management', 'icon' => 'bi-ethernet',
            'description' => 'Switch inventory and port health management.', 'status' => 'Integration ready',
            'stats' => [['Total Switches',(int) config('app.network_inventory_switch_total',0)],['Online',(int) config('app.network_inventory_switch_online',0)],['Offline',max(0,(int) config('app.network_inventory_switch_total',0)-(int) config('app.network_inventory_switch_online',0))]],
            'links' => [['network-inventory','Network Inventory','Back to device overview'],['network-map','Topology','View network topology']],
        ]))->name('network-inventory.switches');

        Route::get('/network-inventory/access-point-management', fn () => view('sweet.device-management', [
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
            return view('sweet.module', [
                'title'=>'Stock Inventory','icon'=>'bi-box-seam','description'=>'Package catalog, stock requests and procurement workflow.',
                'stats'=>[['label'=>'Packages','value'=>$packages],['label'=>'Pending Requests','value'=>$pending],['label'=>'Total Requests','value'=>$total]],
                'links'=>[
                    ['url'=>route('package-list-setup'),'label'=>'Package Catalog','hint'=>'Manage service packages'],
                    ['url'=>route('admin.purchase-requests'),'label'=>'Purchase Requests','hint'=>'Review and process requests'],
                    ['url'=>route('admin.vouchers'),'label'=>'Voucher Inventory','hint'=>'Voucher administration'],
                ],
            ]);
        })->name('sweet.stock-inventory');
        Route::get('/communication-center', function () {
            $templates = \App\Models\SmsTemplate::count();
            $notifications = \App\Models\NotificationLogs::count();
            return view('sweet.module', [
                'title'=>'Communication Center','icon'=>'bi-chat-dots','description'=>'Central SMS, notifications and customer communication tools.',
                'stats'=>[['label'=>'SMS Templates','value'=>$templates],['label'=>'Notifications','value'=>$notifications],['label'=>'Bridge','value'=>'Ready']],
                'links'=>[
                    ['url'=>route('sms-setup'),'label'=>'SMS Setup','hint'=>'Gateway and messaging configuration'],
                    ['url'=>route('sms-bridge.index'),'label'=>'SMS Bridge','hint'=>'Bridge operations'],
                    ['url'=>route('notifications'),'label'=>'Notifications','hint'=>'Notification center'],
                    ['url'=>route('admin-tickets'),'label'=>'Support Tickets','hint'=>'Customer communication'],
                ],
            ]);
        })->name('sweet.communication-center');
        Route::get('/support-center', function () {
            $tickets = \App\Models\SupportTicket::query();
            $open = (clone $tickets)->whereIn('status',['open','pending','in_progress'])->count();
            $total = (clone $tickets)->count();
            return view('sweet.module', [
                'title'=>'Support Center','icon'=>'bi-headset','description'=>'Customer support desk with live ticket visibility and operational logs.',
                'stats'=>[['label'=>'Open Tickets','value'=>$open],['label'=>'Total Tickets','value'=>$total],['label'=>'Status','value'=>'Online']],
                'links'=>[
                    ['url'=>route('admin-tickets'),'label'=>'Support Tickets','hint'=>'Manage customer issues'],
                    ['url'=>route('admin.activity-logs'),'label'=>'Activity Logs','hint'=>'Operational history'],
                    ['url'=>route('admin.login-logs'),'label'=>'Login Logs','hint'=>'Authentication activity'],
                ],
            ]);
        })->name('sweet.support-center');
        Route::get('/team-access', function () {
            $users = \App\Models\User::count();
            $roles = class_exists(\Spatie\Permission\Models\Role::class) ? \Spatie\Permission\Models\Role::count() : 0;
            return view('sweet.module', [
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
        })->name('sweet.team-access');
        Route::get('/system-settings', function () {
            $debug = config('app.debug') ? 'ON' : 'OFF';
            return view('sweet.module', [
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
        })->name('sweet.system-settings');
        Route::get('/billing-helpline', function () {
            $tickets = \App\Models\SupportTicket::query();
            $open = (clone $tickets)->whereIn('status',['open','pending','in_progress'])->count();
            $total = (clone $tickets)->count();
            return view('sweet.module', [
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
        })->name('sweet.billing-helpline');
        Route::get('/profile-security', function () {
            return view('sweet.module', [
                'title'=>'Profile & Security','icon'=>'bi-shield-lock','description'=>'Profile, authentication, sessions and access-security controls.',
                'stats'=>[
                    ['label'=>'Authentication','value'=>'Protected'],
                    ['label'=>'2FA','value'=>'Available'],
                    ['label'=>'Debug','value'=>config('app.debug') ? 'ON' : 'OFF'],
                ],
                'links'=>[
                    ['url'=>route('profile.show'),'label'=>'My Profile','hint'=>'Profile and personal settings'],
                    ['url'=>route('two-factor.login'),'label'=>'Two-Factor Authentication','hint'=>'Manage account 2FA'],
                    ['url'=>route('sweet.team-access'),'label'=>'Team & Access','hint'=>'Users and roles'],
                    ['url'=>route('admin.login-logs'),'label'=>'Login Logs','hint'=>'Authentication events'],
                ],
            ]);
        })->name('sweet.profile-security');

        Route::get('/all-notifications', NotificationListAll::class)->name('notifications');
        // Route::get('/edit-customer', EditCustomer::class);
        // Route::get('/customers', CustomerList::class);

        // Sweet Billing parity aliases — reuse existing production modules.
        Route::get('/mobile-banking', fn () => redirect()->route('sms-setup'))->name('sweet.mobile-banking');
        Route::get('/partner-network', fn () => redirect()->route('admin.resellers.index'))->name('sweet.partner-network');
        Route::get('/bandwidth-reseller', fn () => redirect()->route('reseller.dashboard'))->name('sweet.bandwidth-reseller');
        Route::get('/devices-inventory', fn () => redirect()->route('mikrotik-server'))->name('sweet.devices-inventory');
        Route::get('/stock-inventory', fn () => redirect()->route('admin.purchase-requests'))->name('sweet.stock-inventory');
        Route::get('/communication-center', fn () => redirect()->route('sms-bridge.index'))->name('sweet.communication-center');
        Route::get('/support-center', fn () => redirect()->route('admin-tickets'))->name('sweet.support-center');
        Route::get('/team-access', fn () => redirect()->route('admin-users'))->name('sweet.team-access');
        Route::get('/system-settings', fn () => redirect()->route('site-settings'))->name('sweet.system-settings');
        Route::get('/billing-helpline', fn () => redirect()->route('admin-tickets'))->name('sweet.billing-helpline');
        Route::get('/profile-security', fn () => redirect()->route('profile.show'))->name('sweet.profile-security');

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
