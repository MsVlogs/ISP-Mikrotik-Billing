<?php
namespace App\Livewire;
use App\Models\CustomersInfo;
use Livewire\Component;
use Livewire\WithPagination;
class HighUsageMonitor extends Component
{
    use WithPagination;
    public string $search=''; public string $type='all'; public int $limit=50;
    public function updatedSearch(): void { $this->resetPage(); }
    public function render() {
        $q=CustomersInfo::query()->with('package')->where('status','active')->when($this->search,fn($q)=>$q->where(fn($x)=>$x->where('customer_name','like','%'.$this->search.'%')->orWhere('customer_unique_id','like','%'.$this->search.'%')->orWhere('mobile','like','%'.$this->search.'%')));
        if($this->type==='broadband') $q->whereNotNull('ppp_user_id');
        $users=$q->latest()->paginate($this->limit);
        return view('livewire.high-usage-monitor',compact('users'))->layout('layouts.app');
    }
}
