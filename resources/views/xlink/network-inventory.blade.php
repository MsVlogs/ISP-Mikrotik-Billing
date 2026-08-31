<x-app-layout>
<style>
.network-inventory .ni-card{border:0;border-radius:16px;box-shadow:0 6px 22px rgba(31,41,55,.08);overflow:hidden}.network-inventory .ni-stat{color:#fff;min-height:126px;position:relative}.network-inventory .ni-stat .ni-icon{width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,.18);display:grid;place-items:center;font-size:22px}.network-inventory .ni-stat.blue{background:linear-gradient(135deg,#1683e8,#2b6fe3)}.network-inventory .ni-stat.purple{background:linear-gradient(135deg,#8e3ff0,#a94ee7)}.network-inventory .ni-stat.cyan{background:linear-gradient(135deg,#16a6bd,#1592a6)}.network-inventory .ni-stat.green{background:linear-gradient(135deg,#22a55a,#17b86a)}
.network-inventory .attention-item{display:flex;align-items:center;gap:14px;padding:14px 16px;border-bottom:1px solid #edf0f4}.network-inventory .attention-item:last-child{border-bottom:0}.network-inventory .status-dot{width:42px;height:42px;border-radius:12px;background:#ef4444;display:grid;place-items:center;color:#fff;flex:0 0 auto}.network-inventory .shortcut{display:flex;align-items:center;gap:12px;padding:16px;border-bottom:1px solid #edf0f4;text-decoration:none}.network-inventory .shortcut:last-child{border-bottom:0}.network-inventory .shortcut:hover{background:#f8fafc}.network-inventory .soft{color:#64748b}
</style>
<div class="network-inventory px-1">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="mb-1"><i class="bi bi-hdd-network me-2"></i>Devices Inventory</h3><div class="soft">Central inventory and device health</div></div>
    <span class="badge bg-success-subtle text-success">Live</span></div>
  <div class="row g-3 mb-3">
    <div class="col-md-6 col-xl-3"><div class="ni-card ni-stat blue p-3"><div class="d-flex justify-content-between"><div><div class="small opacity-75">MikroTik Routers</div><div class="fs-2 fw-bold">{{ $routerOnline }} / {{ $routerTotal }}</div><div class="small opacity-75">Online / total · Offline {{ $offlineCount }}</div></div><div class="ni-icon"><i class="bi bi-router"></i></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="ni-card ni-stat purple p-3"><div class="d-flex justify-content-between"><div><div class="small opacity-75">OLT Devices</div><div class="fs-2 fw-bold">{{ $oltOnline }} / {{ $oltTotal }}</div><div class="small opacity-75">Online / total · Offline {{ max(0,$oltTotal-$oltOnline) }}</div></div><div class="ni-icon"><i class="bi bi-diagram-3"></i></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="ni-card ni-stat cyan p-3"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Switches</div><div class="fs-2 fw-bold">{{ $switchOnline }} / {{ $switchTotal }}</div><div class="small opacity-75">Online / total · Offline {{ max(0,$switchTotal-$switchOnline) }}</div></div><div class="ni-icon"><i class="bi bi-ethernet"></i></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="ni-card ni-stat green p-3"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Access Points</div><div class="fs-2 fw-bold">{{ $apOnline }} / {{ $apTotal }}</div><div class="small opacity-75">Online / total · Offline {{ max(0,$apTotal-$apOnline) }}</div></div><div class="ni-icon"><i class="bi bi-wifi"></i></div></div></div></div>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-md-6 col-xl-3"><div class="ni-card bg-white p-3"><div class="small soft">24h Uptime</div><div class="fs-3 fw-bold">{{ $uptime24 !== null ? $uptime24.'%' : '—' }}</div></div></div>
    <div class="col-md-6 col-xl-3"><div class="ni-card bg-white p-3"><div class="small soft">7d Uptime</div><div class="fs-3 fw-bold">{{ $uptime7 !== null ? $uptime7.'%' : '—' }}</div></div></div>
    <div class="col-md-6 col-xl-3"><div class="ni-card bg-white p-3"><div class="small soft">7d Incidents</div><div class="fs-3 fw-bold {{ $incidentCount ? 'text-danger' : 'text-success' }}">{{ $incidentCount }}</div></div></div>
    <div class="col-md-6 col-xl-3"><div class="ni-card bg-white p-3"><div class="small soft">24h Avg Latency</div><div class="fs-3 fw-bold">{{ $avgLatency !== null ? $avgLatency.' ms' : '—' }}</div></div></div>
  </div>
  <div class="row g-3">
    <div class="col-xl-7"><div class="ni-card bg-white"><div class="p-3 border-bottom"><div class="fw-bold fs-5">Live Inventory</div><div class="fw-semibold">Offline / Attention Needed <span class="badge bg-danger ms-1">{{ $offlineCount + max(0,$oltTotal-$oltOnline) + max(0,$switchTotal-$switchOnline) + max(0,$apTotal-$apOnline) }}</span></div></div>
      @forelse($attention as $device)
        <div class="attention-item"><div class="status-dot"><i class="bi bi-hdd-network"></i></div><div class="flex-grow-1"><div class="fw-semibold">{{ $device->display_name ?? $device->router_name }}</div><div class="small soft">{{ $device->device_type ?? 'MikroTik' }} · {{ $device->ip_address ?? 'No IP' }} · {{ ucfirst($device->health_status ?? 'down') }}</div></div><div class="small soft">{{ optional($device->last_checked_at ?? $device->updated_at)->format('d M H:i') }}</div></div>
      @empty
        <div class="p-4 text-center soft">No devices require attention.</div>
      @endforelse
    </div></div>
    <div class="col-xl-5"><div class="ni-card bg-white"><div class="p-3 border-bottom"><div class="fw-bold fs-5">Shortcuts</div><div class="soft">Quick Management</div></div>
      <a class="shortcut" href="{{ route('mikrotik-server') }}" wire:navigate.hover><i class="bi bi-router fs-5"></i><span>MikroTik Management</span></a>
      <a class="shortcut" href="{{ route('network-map') }}" wire:navigate.hover><i class="bi bi-diagram-3 fs-5"></i><span>Network Map</span></a>
      <a class="shortcut" href="{{ route('mikrotik-server-backup') }}" wire:navigate.hover><i class="bi bi-hdd-stack fs-5"></i><span>Server Backup</span></a>
      <a class="shortcut" href="{{ route('device-watcher') }}" wire:navigate.hover><i class="bi bi-eye fs-5"></i><span>Device Watcher</span></a>
      <a class="shortcut" href="{{ route('network-inventory.health-history') }}"><i class="bi bi-activity fs-5"></i><span>Health History</span></a>
    </div></div>
  </div>
</div>
</x-app-layout>
