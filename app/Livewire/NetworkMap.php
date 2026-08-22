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
        $routers=RouterList::where('action','connected')->orderBy('router_name')->get();
        $nodes=CustomersAddress::query()->whereNotNull('latitude')->whereNotNull('longitude')->limit(1000)->get()->map(fn($a)=>['lat'=>(float)$a->latitude,'lng'=>(float)$a->longitude,'id'=>$a->customer_address_unique_id,'label'=>$a->label_name,'status'=>'customer'])->values();
        $customers=CustomersInfo::active()->count();
        return view('livewire.network-map',compact('routers','nodes','customers'))->layout('layouts.app');
    }
}
