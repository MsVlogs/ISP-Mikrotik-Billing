<?php
namespace App\Livewire;
use App\Models\CustomersAddress;
use App\Models\CustomersInfo;
use App\Models\RouterList;
use App\Models\NetworkInventoryDevice;
use Livewire\Component;
class NetworkMap extends Component
{
    public function mount(): void { if (! hasAccess(['Super Admin'], ['mikrotik-setup'])) abort(403); }
    public function render() {
        $routers = RouterList::orderBy('router_name')->get();
        $routerNodes = $routers->filter(fn($r) => $r->latitude !== null && $r->longitude !== null)->map(fn($r)=>[
            'lat'=>(float)$r->latitude,'lng'=>(float)$r->longitude,'id'=>$r->id,'label'=>$r->router_name,'ip'=>$r->ip_address,
            'location'=>$r->location,'status'=>$r->action === 'connected' ? 'online' : 'offline','kind'=>'router','router'=>$r->router_name,
        ])->values()->toArray();
        $deviceLocations = NetworkInventoryDevice::query()->whereIn('type',['olt','onu','switch','wifi-router','access-point'])->get()->map(function($d){
            $lat=$d->latitude; $lng=$d->longitude;
            if (($lat === null || $lng === null) && is_string($d->location) && preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/',$d->location,$m)) { $lat=(float)$m[1]; $lng=(float)$m[2]; }
            if ($lat === null || $lng === null) return null;
            return ['lat'=>(float)$lat,'lng'=>(float)$lng,'id'=>$d->id,'label'=>$d->name,'status'=>$d->health_status ?: ($d->status ?: 'unknown'),'router'=>null,'kind'=>$d->type,'ip'=>$d->ip_address,'location'=>$d->location];
        })->filter()->values()->toArray();
        $customers = CustomersInfo::active()->count();
        $nodes = CustomersAddress::query()->leftJoin('customers_infos as ci','ci.customer_unique_id','=','customers_addresses.customer_address_unique_id')->leftJoin('p_p_p_secrets as ps','ps.id','=','ci.ppp_user_id')
            ->whereNotNull('customers_addresses.latitude')->whereNotNull('customers_addresses.longitude')->limit(1000)
            ->get(['customers_addresses.latitude','customers_addresses.longitude','customers_addresses.customer_address_unique_id','customers_addresses.label_name','ci.status as customer_status','ps.router_name'])
            ->map(fn($a)=>['lat'=>(float)$a->latitude,'lng'=>(float)$a->longitude,'id'=>$a->customer_address_unique_id,'label'=>$a->label_name,'status'=>$a->customer_status ?: 'unknown','router'=>$a->router_name,'kind'=>'customer'])->values()->toArray();
        $nodes = collect(array_merge($nodes, $routerNodes, $deviceLocations));
        return view('livewire.network-map',compact('routers','nodes','customers','routerNodes','deviceLocations'))->layout('layouts.app');
    }
}