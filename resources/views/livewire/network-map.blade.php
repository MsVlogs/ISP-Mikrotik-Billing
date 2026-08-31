@push('styles')
<link rel="stylesheet" href="{{ asset('xlink-network-monitoring/network-monitoring-polish.css') }}">
<link rel="stylesheet" href="{{ asset('xlink-network-monitoring/network-map-polish.css') }}">
@endpush
<div class="container-fluid py-3">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="mb-1"><i class="bi bi-diagram-3 me-2"></i>Network Map</h3><small class="text-muted">Router, OLT, ONU, WiFi Router and customer locations</small></div><span class="badge bg-success">{{ $routers->where('action','connected')->count() }} routers online</span></div>
<div class="row g-3 mb-3">
<div class="col-md-3"><div class="card"><div class="card-body"><small>Active Customers</small><h4>{{ number_format($customers) }}</h4></div></div></div>
<div class="col-md-3"><div class="card"><div class="card-body"><small>Map Devices</small><h4>{{ number_format($routerNodes ? count($routerNodes) : 0) + $deviceLocations->count() }}</h4></div></div></div>
<div class="col-md-6"><div class="card"><div class="card-body"><div class="map-device-legend"><span>🛜 MikroTik Router</span><span>🔷 OLT</span><span>🔹 ONU</span><span>📶 WiFi Router</span><span>📡 Access Point</span><span>● Customer</span></div></div></div></div>
</div>
<div class="card"><div class="card-body">
<div class="row g-2 mb-3">
<div class="col-md-3"><label class="form-label">Device / Network Type</label><select id="map-type-filter" class="form-select"><option value="">All Devices</option><option value="router">MikroTik Router</option><option value="olt">OLT</option><option value="onu">ONU</option><option value="wifi-router">WiFi Router</option><option value="access-point">Access Point</option><option value="customer">Customer</option></select></div>
<div class="col-md-3"><label class="form-label">Router</label><select id="map-router-filter" class="form-select"><option value="">All Routers</option>@foreach($routers as $r)<option value="{{ $r->router_name }}">{{ $r->router_name }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Status</label><select id="map-status-filter" class="form-select"><option value="">All Status</option><option value="online">Online</option><option value="offline">Offline</option><option value="unknown">Unknown</option></select></div>
<div class="col-md-3"><label class="form-label">Search</label><input id="map-customer-search" class="form-control" placeholder="Name, IP, ID or location"></div>
</div>
<div id="network-map" style="height:620px;border-radius:.5rem"></div>
</div></div>
</div>
<script>
document.addEventListener('livewire:navigated',()=>{if(!window.L){const s=document.createElement('script');s.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';document.head.appendChild(s);s.onload=()=>window.initNetworkMap?.();}else window.initNetworkMap?.();});
window.initNetworkMap=()=>{const el=document.getElementById('network-map');if(!el||el._map)return;const map=L.map(el).setView([23.8103,90.4125],11);el._map=map;L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
const nodes=@json($nodes);let layers=[];const esc=v=>String(v??'').replace(/</g,'&lt;').replace(/>/g,'&gt;');
const meta={router:['MikroTik Router','🛜'],olt:['OLT','🔷'],onu:['ONU','🔹'],'wifi-router':['WiFi Router','📶'],'access-point':['Access Point','📡'],customer:['Customer','●']};
const render=()=>{layers.forEach(x=>map.removeLayer(x));layers=[];const tf=document.getElementById('map-type-filter')?.value||'',rf=document.getElementById('map-router-filter')?.value||'',sf=document.getElementById('map-status-filter')?.value||'',q=(document.getElementById('map-customer-search')?.value||'').toLowerCase().trim();const filtered=nodes.filter(n=>(!tf||n.kind===tf)&&(!rf||n.router===rf)&&(!sf||n.status===sf)&&(!q||[n.id,n.label,n.ip,n.location].some(v=>String(v??'').toLowerCase().includes(q))));
filtered.forEach(n=>{const mta=meta[n.kind]||['Device','📍'];const icon=L.divIcon({className:'network-'+n.kind+'-marker',html:mta[1],iconSize:[28,28],iconAnchor:[14,14]});const m=L.marker([n.lat,n.lng],{icon}).bindPopup('<strong>'+mta[0]+'</strong><br>'+esc(n.label)+'<br>IP: '+esc(n.ip||'—')+'<br>Location: '+esc(n.location||'—')+'<br>Status: '+esc(n.status||'unknown')).addTo(map);layers.push(m);});
const allPts=filtered.map(n=>[n.lat,n.lng]);if(allPts.length)map.fitBounds(L.latLngBounds(allPts),{padding:[30,30],maxZoom:15});setTimeout(()=>map.invalidateSize(),100);};
['map-type-filter','map-router-filter','map-status-filter','map-customer-search'].forEach(id=>document.getElementById(id)?.addEventListener(id==='map-customer-search'?'input':'change',render));render();};
</script><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
