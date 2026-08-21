<?php
namespace App\Livewire;
use App\Models\DeviceWatcher as Watcher;
use App\Models\RouterList;
use Livewire\Component;
class DeviceWatcher extends Component
{
    public bool $showForm=false; public ?int $editingId=null; public string $name='', $router_name='', $host='', $port='8728', $interval_seconds='60', $threshold_ms='1000'; public bool $enabled=true;
    public function mount(): void { if (! hasAccess(['Super Admin'], ['mikrotik-setup'])) abort(403); }
    public function save(): void { $this->validate(['name'=>'required|string|max:100','host'=>'required|string|max:255','port'=>'required|integer|min:1|max:65535','interval_seconds'=>'required|integer|min:10|max:3600','threshold_ms'=>'required|integer|min:1|max:60000']); Watcher::updateOrCreate(['id'=>$this->editingId],['name'=>$this->name,'router_name'=>$this->router_name?:null,'host'=>$this->host,'port'=>(int)$this->port,'interval_seconds'=>(int)$this->interval_seconds,'threshold_ms'=>(int)$this->threshold_ms,'enabled'=>$this->enabled]); $this->resetForm(); }
    public function edit(int $id): void { $w=Watcher::findOrFail($id); $this->editingId=$w->id; $this->name=$w->name; $this->router_name=$w->router_name??''; $this->host=$w->host; $this->port=(string)$w->port; $this->interval_seconds=(string)$w->interval_seconds; $this->threshold_ms=(string)$w->threshold_ms; $this->enabled=(bool)$w->enabled; $this->showForm=true; }
    public function delete(int $id): void { Watcher::findOrFail($id)->delete(); }
    public function check(int $id): void { $w=Watcher::findOrFail($id); $start=microtime(true); $ok=false; $errno=0; $err=''; $s=@fsockopen($w->host,$w->port,$errno,$err,3); if($s){$ok=true;fclose($s);} $w->update(['last_status'=>$ok?'online':'down','last_latency_ms'=>(int)round((microtime(true)-$start)*1000),'last_checked_at'=>now()]); }
    public function resetForm(): void { $this->reset(['showForm','editingId','name','router_name','host']); $this->port='8728'; $this->interval_seconds='60'; $this->threshold_ms='1000'; $this->enabled=true; }
    public function render(){ return view('livewire.device-watcher',['watchers'=>Watcher::latest()->get(),'routers'=>RouterList::where('action','connected')->orderBy('router_name')->get()])->layout('layouts.app'); }
}
