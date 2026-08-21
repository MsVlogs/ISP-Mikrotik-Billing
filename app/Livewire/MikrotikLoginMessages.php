<?php
namespace App\Livewire;
use App\Models\MikrotikLog;
use App\Models\RouterList;
use Livewire\Component;
use Livewire\WithPagination;
class MikrotikLoginMessages extends Component
{ use WithPagination; public string $router=''; public string $event=''; public string $search=''; public function sync(): void { if($this->router){try{app(\App\Http\Controllers\MikrotikController::class)->storeRouterLogs($this->router,app(\App\Http\Controllers\MikrotikController::class)->getRouterLogs($this->router,200));}catch(\Throwable $e){report($e);}} } public function render(){ $q=MikrotikLog::query()->when($this->router,fn($q)=>$q->where('router_name',$this->router))->when($this->search,fn($q)=>$q->where('message','like','%'.$this->search.'%')); $logs=$q->latest()->paginate(50); $base=MikrotikLog::query()->when($this->router,fn($q)=>$q->where('router_name',$this->router)); return view('livewire.mikrotik-login-messages',['logs'=>$logs,'routers'=>RouterList::where('action','connected')->pluck('router_name'),'login'=>(clone $base)->where('message','like','%login%')->count(),'logout'=>(clone $base)->where('message','like','%logout%')->count(),'failed'=>(clone $base)->where(fn($q)=>$q->where('message','like','%fail%')->orWhere('message','like','%auth%fail%'))->count()])->layout('layouts.app'); } }
