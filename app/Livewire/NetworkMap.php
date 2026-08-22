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
        $nodes=CustomersAddress::query()->leftJoin('customers_infos as ci','ci.customer_unique_id','=','customers_addresses.customer_address_unique_id')->leftJoin('p_p_p_secrets as ps','ps.id','=','ci.ppp_user_id')->whereNotNull('customers_addresses.latitude')->whereNotNull('customers_addresses.longitude')->limit(1000)->get(['customers_addresses.latitude','customers_addresses.longitude','customers_addresses.customer_address_unique_id','customers_addresses.label_name','ci.status as customer_status','ps.router_name'])->map(fn($a)=>['lat'=>(float)$a->latitude,'lng'=>(float)$a->longitude,'id'=>$a->customer_address_unique_id,'label'=>$a->label_name,'status'=>$a->customer_status ?: 'unknown','router'=>$a->router_name])->values();
        $customers=CustomersInfo::active()->count();
        return view('livewire.network-map',compact('routers','nodes','customers'))->layout('layouts.app');
    }
}
