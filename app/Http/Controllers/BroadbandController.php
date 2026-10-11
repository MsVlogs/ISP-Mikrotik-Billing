<?php

namespace App\Http\Controllers;

use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\Reseller;
use App\Models\RouterList;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BroadbandController extends Controller
{
    private function baseQuery(Request $request)
    {
        return CustomersInfo::query()
            ->with(['billing', 'pppUser', 'customerAddress', 'package', 'reseller'])
            ->when($request->q, fn ($q, $v) => $q->where(function ($x) use ($v) {
                $like = "%{$v}%";
                $x->where('customer_name', 'like', $like)
                  ->orWhere('customer_unique_id', 'like', $like)
                  ->orWhere('mobile', 'like', $like)
                  ->orWhere('alternative_mobile', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('contact_person', 'like', $like)
                  ->orWhere('parents_name', 'like', $like)
                  ->orWhere('spouse_name', 'like', $like)
                  ->orWhere('address', 'like', $like)
                  ->orWhere('identification_no', 'like', $like)
                  ->orWhere('profession', 'like', $like)
                  ->orWhereHas('customerAddress', function ($address) use ($like) {
                      $address->where('label_name', 'like', $like)
                          ->orWhere('input_type_text', 'like', $like)
                          ->orWhere('input_type_dropdown', 'like', $like)
                          ->orWhere('input_type_textarea', 'like', $like);
                  })
                  ->orWhereHas('pppUser', function ($ppp) use ($like) {
                      $ppp->where('username', 'like', $like)
                          ->orWhere('router_name', 'like', $like)
                          ->orWhere('ppp_remote_ip', 'like', $like)
                          ->orWhere('caller_id', 'like', $like)
                          ->orWhere('comment', 'like', $like);
                  })
                  ->orWhereHas('package', function ($package) use ($like) {
                      $package->where('package', 'like', $like);
                  });
            }))
            ->when($request->router_id, fn ($q, $v) => $q->whereHas('pppUser', fn ($x) => $x->where('router_name', $v)))
            ->when($request->reseller_id, fn ($q, $v) => $q->where('reseller_id', $v));
    }

    public function list(Request $request)
    {
        $status = $request->status;
        $query = $this->baseQuery($request);
        if ($status === 'online') $query->where('status', 'active')->whereHas('pppUser', fn ($q) => $q->where('status', 'active'));
        if ($status === 'inactive') $query->whereIn('status', ['inactive', 'disable']);
        if ($status === 'unverified') $query->where(function ($q) { $q->whereNull('mobile')->orWhereNull('identification_no'); });
        if ($status === 'due') $query->whereHas('billing', fn ($q) => $q->where('due_amount', '>', 0));
        if ($request->from) $query->whereDate('created_at', '>=', $request->from);
        if ($request->to) $query->whereDate('created_at', '<=', $request->to);
        $rows = min(max((int) $request->input('rows', 50), 10), 500);
        $customers = $query->latest('id')->paginate($rows)->withQueryString();
        return view('xlink.broadband.customer-list', ['customers' => $customers, 'mode' => $status ?: 'all', 'routers' => RouterList::orderBy('router_name')->get(), 'resellers' => Reseller::with('user')->get(), 'packages' => PackageList::orderBy('package')->get()]);
    }

    public function search(Request $request)
    {
        $customers = $this->baseQuery($request)->latest('id')->paginate(50)->withQueryString();
        return view('xlink.broadband.search', compact('customers'));
    }

    public function due(Request $request)
    { $request->merge(['status' => 'due']); return $this->list($request); }
    public function inactive(Request $request)
    { $request->merge(['status' => 'inactive']); return $this->list($request); }
    public function newCustomers(Request $request)
    { $request->merge(['from' => Carbon::now()->toDateString()]); return $this->list($request); }
    public function unverified(Request $request)
    { $request->merge(['status' => 'unverified']); return $this->list($request); }

    public function customerPing(string $id): JsonResponse
    {
        abort_unless(auth()->user()?->hasRole('Super Admin') || hasAccess(['Super Admin'], ['mikrotik-setup']), 403);

        try {
            $uniqueId = decrypt($id);
            $customer = CustomersInfo::where('customer_unique_id', $uniqueId)->with('pppUser')->firstOrFail();
            $ip = $customer->pppUser?->ppp_remote_ip ?: $customer->pppUser?->ip_address;

            if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No valid customer IP is available for ping.',
                    'ip' => $ip,
                ], 422);
            }

            $isIpv6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
            $binary = $isIpv6 ? 'ping6' : 'ping';
            $command = $binary.' -c 1 -W 2 '.escapeshellarg($ip);
            $startedAt = microtime(true);

            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open($command, $descriptorSpec, $pipes);

            if (! is_resource($process)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Ping service could not be started.',
                    'ip' => $ip,
                ], 503);
            }

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);
            $latency = null;

            if (preg_match('/time[=<]([0-9.]+)\s*ms/i', (string) $stdout, $match)) {
                $latency = round((float) $match[1], 2);
            }

            if ($exitCode === 0) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Host is reachable.',
                    'ip' => $ip,
                    'latency_ms' => $latency ?? round((microtime(true) - $startedAt) * 1000, 2),
                ]);
            }

            return response()->json([
                'ok' => false,
                'message' => 'Host did not reply to ping.',
                'ip' => $ip,
                'latency_ms' => $latency,
                'detail' => trim((string) $stderr),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Ping check failed.',
            ], 500);
        }
    }

    public function customerTraffic(string $id): JsonResponse
    {
        abort_unless(auth()->user()?->hasRole('Super Admin') || hasAccess(['Super Admin'], ['mikrotik-setup']), 403);

        try {
            $uniqueId = decrypt($id);
            $customer = CustomersInfo::where('customer_unique_id', $uniqueId)
                ->with('pppUser')
                ->firstOrFail();

            $ppp = $customer->pppUser;

            if (! $ppp || ! $ppp->router_name || ! $ppp->username) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No active MikroTik PPP session information is available.',
                ], 422);
            }

            if ($customer->status !== 'active' || $ppp->status !== 'active') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Customer is no longer online.',
                ], 409);
            }

            $interface = '<pppoe-'.$ppp->username.'>';
            $data = app(MikrotikController::class)->getLiveTraffic($ppp->router_name, $interface);

            return response()->json([
                'ok' => true,
                'customer' => $customer->customer_unique_id,
                'username' => $ppp->username,
                'router' => $ppp->router_name,
                'interface' => $interface,
                'rx_bps' => (int) ($data['rx-bits-per-second'] ?? 0),
                'tx_bps' => (int) ($data['tx-bits-per-second'] ?? 0),
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Live traffic check failed.',
            ], 500);
        }
    }

    public function disableCustomer(string $id)
    {
        abort_unless(auth()->user()?->hasRole('Super Admin') || hasAccess(['Super Admin'], ['disable-customer']), 403);

        try {
            $uniqueId = decrypt($id);
            $customer = CustomersInfo::where('customer_unique_id', $uniqueId)->with('pppUser')->firstOrFail();

            if ($customer->pppUser && $customer->pppUser->router_name) {
                app(MikrotikController::class)->disablePPPSecret(
                    $uniqueId,
                    $customer->pppUser->router_name,
                    $customer->pppUser->username
                );
            }

            \DB::transaction(function () use ($customer) {
                $customer->status = 'disable';
                $customer->save();

                if ($customer->pppUser) {
                    PPPSecrets::whereKey($customer->ppp_user_id)->update(['status' => 'disable']);
                }
            });

            return back()->with('broadband_message', 'Customer disabled successfully.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['customer' => 'Failed to disable customer. Please try again.']);
        }
    }

    public function destroyCustomer(string $id)
    {
        abort_unless(auth()->user()?->hasRole('Super Admin') || hasAccess(['Super Admin'], ['delete-customer']), 403);

        try {
            $uniqueId = decrypt($id);
            $customer = CustomersInfo::where('customer_unique_id', $uniqueId)->with('pppUser')->firstOrFail();

            if ($customer->status === 'active') {
                return back()->withErrors(['customer' => 'Disable the customer before deleting the customer record.']);
            }

            $pppUser = $customer->pppUser;
            if ($pppUser && $pppUser->router_name) {
                app(MikrotikController::class)->removePPPSecret(
                    $uniqueId,
                    $pppUser->router_name,
                    $pppUser->username
                );
            }

            \DB::transaction(function () use ($customer, $pppUser) {
                if ($pppUser) {
                    $pppUser->delete();
                }
                $customer->delete();
            });

            return redirect()->route('broadband-customers')->with('broadband_message', 'Customer deleted successfully.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['customer' => 'Operation failed. Please try again.']);
        }
    }

    public function packages(Request $request)
    {
        $packages = PackageList::query()->orderBy('package')->paginate(50)->withQueryString();
        return view('xlink.broadband.packages', compact('packages'));
    }

    public function import()
    {
        return view('xlink.broadband.import', ['routers' => RouterList::orderBy('router_name')->get(), 'packages' => PackageList::orderBy('package')->get()]);
    }

    public function storePackage(Request $request)
    {
        $data = $request->validate(['package' => 'required|string|max:120', 'price' => 'required|numeric|min:0']);
        PackageList::create($data + ['status' => 'active']);
        return back()->with('broadband_message', 'Package created successfully.');
    }
}
