<?php
namespace App\Livewire;
use App\Models\CustomersAddress;
use App\Models\CustomersInfo;
use App\Models\RouterList;
use Livewire\Component;
class NetworkMap extends Component
{
    public function mount(): void { if (! hasAccess(['Super Admin'], ['mikrotik-setup'])) abort(403); }
    public function render() {
        $routers=RouterList::orderBy('router_name')->get();
        $routerNodes = $routers->whereNotNull('latitude')->whereNotNull('longitude')->map(fn($r)=>[
            'lat'=>(float)$r->latitude,'lng'=>(float)$r->longitude,'name'=>$r->router_name,
            'ip'=>$r->ip_address,'location'=>$r->location,'status'=>$r->action === 'connected' ? 'online' : 'offline',
        ])->values();
        $oltNodes = \App\Models\NetworkInventoryDevice::query()->where('type','olt')
            ->whereNotNull('latitude')->whereNotNull('longitude')->get()->map(fn($o)=>[
                'lat'=>(float)$o->latitude,'lng'=>(float)$o->longitude,'name'=>$o->name,'ip'=>$o->ip_address,
                'location'=>$o->location,'status'=>$o->health_status ?: ($o->status ?: 'unknown'),
                'type'=>'OLT',
            ])->values();
        $nodes=CustomersAddress::query()->leftJoin('customers_infos as ci','ci.customer_unique_id','=','customers_addresses.customer_address_unique_id')->leftJoin('p_p_p_secrets as ps','ps.id','=','ci.ppp_user_id')->whereNotNull('customers_addresses.latitude')->whereNotNull('customers_addresses.longitude')->limit(1000)->get(['customers_addresses.latitude','customers_addresses.longitude','customers_addresses.customer_address_unique_id','customers_addresses.label_name','ci.status as customer_status','ps.router_name'])->map(fn($a)=>['lat'=>(float)$a->latitude,'lng'=>(float)$a->longitude,'id'=>$a->customer_address_unique_id,'label'=>$a->label_name,'status'=>$a->customer_status ?: 'unknown','router'=>$a->router_name,'kind'=>'customer'])->values()->toArray();
        $routers=RouterList::orderBy('router_name')->get();
        $deviceLocations=\App\Models\NetworkInventoryDevice::query()->whereIn('type',['olt','switch','access-point'])->whereNotNull('latitude')->whereNotNull('longitude')->get()->map(fn($d)=>['lat'=>(float)$d->latitude,'lng'=>(float)$d->longitude,'id'=>$d->id,'label'=>$d->name,'status'=>$d->health_status ?: $d->status ?: 'unknown','router'=>null,'kind'=>$d->type,'ip'=>$d->ip_address,'location'=>$d->location])->values()->toArray();
        $routerLocations=$routers->filter(fn($r)=>$r->latitude!==null && $r->longitude!==null)->map(fn($r)=>['lat'=>(float)$r->latitude,'lng'=>(float)$r->longitude,'id'=>$r->id,'label'=>$r->router_name,'status'=>$r->action === 'connected' ? 'online' : 'offline','router'=>$r->router_name,'kind'=>'router','ip'=>$r->ip_address])->values()->toArray();
        $nodes=collect(array_merge($nodes,$routerLocations,$deviceLocations));
        $customers=CustomersInfo::active()->count();
        return view('livewire.network-map',compact('routers','nodes','customers','routerNodes','oltNodes'))->layout('layouts.app');
    }
}
