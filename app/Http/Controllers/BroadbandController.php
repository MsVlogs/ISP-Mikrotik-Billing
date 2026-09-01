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

class BroadbandController extends Controller
{
    private function baseQuery(Request $request)
    {
        return CustomersInfo::query()
            ->with(['billing', 'pppUser', 'customerAddress', 'package', 'reseller'])
            ->when($request->q, fn ($q, $v) => $q->where(function ($x) use ($v) {
                $x->where('customer_name', 'like', "%{$v}%")
                  ->orWhere('customer_unique_id', 'like', "%{$v}%")
                  ->orWhere('mobile', 'like', "%{$v}%")
                  ->orWhere('contact_email', 'like', "%{$v}%");
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
        if ($status === 'unverified') $query->where(function ($q) { $q->whereNull('mobile')->orWhereNull('nid'); });
        if ($status === 'due') $query->whereHas('billing', fn ($q) => $q->where('due_amount', '>', 0));
        if ($request->from) $query->whereDate('created_at', '>=', $request->from);
        if ($request->to) $query->whereDate('created_at', '<=', $request->to);
        $customers = $query->latest('id')->paginate((int) ($request->rows ?: 50))->withQueryString();
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
