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

        // Sweet Billing control center
        Route::get('/sweet-billing', fn () => view('sweet.index', [
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
        ]))->name('sweet.index');

        // Sweet Billing functional module hubs — backed by existing production features.
        Route::get('/mobile-banking', fn () => view('sweet.module', [
            'title'=>'Mobile Banking','icon'=>'bi-phone','description'=>'Mobile payment, SMS and collection operations.',
            'stats'=>[['Gateway','Ready'],['SMS','Ready'],['Collections','Live']],
            'links'=>[[route('sms-setup'),'SMS Setup','Gateway configuration'],[route('sms-bridge.index'),'SMS Bridge','Bridge operations'],[route('payment-collection'),'Payment Collection','Collection desk']],
        ]))->name('sweet.mobile-banking');
        Route::get('/partner-network', fn () => view('sweet.module', [
            'title'=>'Partner Network','icon'=>'bi-diagram-3','description'=>'Partner and reseller management.',
            'stats'=>[['Partners','Live'],['Requests','Live'],['Access','Protected']],
            'links'=>[[route('admin.resellers.index'),'Partner Management','Create and manage partners'],[route('admin.purchase-requests'),'Requests','Review requests'],[route('reseller.dashboard'),'Partner Dashboard','Reseller operations']],
        ]))->name('sweet.partner-network');
        Route::get('/bandwidth-reseller', fn () => view('sweet.module', [
            'title'=>'Bandwidth Reseller','icon'=>'bi-speedometer2','description'=>'Reseller packages, wallet, vouchers and customers.',
            'stats'=>[['Packages','Ready'],['Wallet','Live'],['Vouchers','Live']],
            'links'=>[[route('reseller.dashboard'),'Reseller Dashboard','Operations'],[route('reseller.packages.index'),'Packages','Package management'],[route('reseller.wallet.index'),'Wallet','Balances'],[route('reseller.vouchers.index'),'Vouchers','Voucher management']],
        ]))->name('sweet.bandwidth-reseller');
        Route::get('/devices-inventory', fn () => view('sweet.module', [
            'title'=>'Devices Inventory','icon'=>'bi-hdd-network','description'=>'Router inventory, topology and device health.',
            'stats'=>[['Routers','Live'],['Topology','Ready'],['Watchers','Ready']],
            'links'=>[[route('mikrotik-server'),'MikroTik Server','Router inventory'],[route('network-map'),'Network Map','Topology'],[route('device-watcher'),'Device Watcher','Health checks']],
        ]))->name('sweet.devices-inventory');
        Route::get('/stock-inventory', fn () => view('sweet.module', [
            'title'=>'Stock Inventory','icon'=>'bi-box-seam','description'=>'Stock and package-request workflows.',
            'stats'=>[['Requests','Live'],['Packages','Ready']],
            'links'=>[[route('admin.purchase-requests'),'Purchase Requests','Review requests'],[route('package-list-setup'),'Packages','Catalog']],
        ]))->name('sweet.stock-inventory');
        Route::get('/communication-center', fn () => view('sweet.module', [
            'title'=>'Communication Center','icon'=>'bi-chat-dots','description'=>'SMS, notifications and customer communication.',
            'stats'=>[['SMS','Ready'],['Bridge','Ready'],['Alerts','Live']],
            'links'=>[[route('sms-setup'),'SMS Setup','Gateway settings'],[route('sms-bridge.index'),'SMS Bridge','Bridge management'],[route('notifications'),'Notifications','Notification center']],
        ]))->name('sweet.communication-center');
        Route::get('/support-center', fn () => view('sweet.module', [
            'title'=>'Support Center','icon'=>'bi-headset','description'=>'Customer support and operational assistance.',
            'stats'=>[['Tickets','Live'],['Logs','Live']],
            'links'=>[[route('admin-tickets'),'Support Tickets','Manage tickets'],[route('admin.activity-logs'),'Activity Logs','Operational history']],
        ]))->name('sweet.support-center');
        Route::get('/team-access', fn () => view('sweet.module', [
            'title'=>'Team & Access','icon'=>'bi-people','description'=>'Users, roles and access administration.',
            'stats'=>[['Users','Protected'],['Roles','Protected'],['Auth','Active']],
            'links'=>[[route('admin-users'),'Manage Users','User administration'],[route('admin-roles'),'Manage Roles','Permissions'],[route('profile.show'),'My Profile','Account']],
        ]))->name('sweet.team-access');
        Route::get('/system-settings', fn () => view('sweet.module', [
            'title'=>'System Settings','icon'=>'bi-gear','description'=>'Application, branding, MikroTik and messaging configuration.',
            'stats'=>[['Environment',app()->environment()],['Debug',config('app.debug') ? 'ON' : 'OFF'],['Status','Online']],
            'links'=>[[route('site-settings'),'Site Settings','Application settings'],[route('mikrotik-sync'),'MikroTik Setup','Router integration'],[route('sms-setup'),'SMS Setup','Messaging']],
        ]))->name('sweet.system-settings');
        Route::get('/billing-helpline', fn () => view('sweet.module', [
            'title'=>'Billing Helpline','icon'=>'bi-telephone','description'=>'Billing support and collection assistance.',
            'stats'=>[['Billing','Online'],['Support','Ready'],['Reports','Live']],
            'links'=>[[route('admin-tickets'),'Support Tickets','Customer support'],[route('payment-collection'),'Payment Collection','Collection'],[route('collection-report.index'),'Collection Report','Reports']],
        ]))->name('sweet.billing-helpline');
        Route::get('/profile-security', fn () => view('sweet.module', [
            'title'=>'Profile & Security','icon'=>'bi-shield-lock','description'=>'Account profile, access and authentication controls.',
            'stats'=>[['Authentication','Protected'],['Debug',config('app.debug') ? 'ON' : 'OFF'],['Session','Secure']],
            'links'=>[[route('profile.show'),'My Profile','Account settings'],[route('sweet.team-access'),'Team & Access','Users and roles'],[route('admin.login-logs'),'Login Logs','Authentication events']],
        ]))->name('sweet.profile-security');

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
