@push('styles')
<link rel="stylesheet" href="{{ asset('xlink-network-monitoring/network-monitoring-polish.css') }}">
<link rel="stylesheet" href="{{ asset('xlink-network-monitoring/network-map-polish.css') }}">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush
<div class="container-fluid py-3 network-map-page">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h3 class="mb-1"><i class="bi bi-diagram-3 me-2"></i>Network Map</h3><small class="text-muted">Geographic view of routers, OLT/ONU devices, access points and mapped customers</small></div><div class="d-flex align-items-center gap-2"><span class="badge bg-success">{{ $routers->where('action','connected')->count() }} routers online</span><a class="btn btn-sm btn-outline-primary" href="{{ route('network-topology.live') }}"><i class="bi bi-diagram-3 me-1"></i>Live Topology</a></div></div>
<div class="row g-3 mb-3">
<div class="col-md-3"><div class="card map-stat-card"><div class="card-body"><small>Active Customers</small><h4>{{ number_format($customers) }}</h4><span class="text-muted small">Billing accounts</span></div></div></div>
<div class="col-md-3"><div class="card map-stat-card"><div class="card-body"><small>Mapped Nodes</small><h4>{{ number_format($nodes->count()) }}</h4><span class="text-muted small">With valid coordinates</span></div></div></div>
<div class="col-md-6"><div class="card map-stat-card"><div class="card-body"><div class="map-device-legend"><span><i class="map-legend-dot router"></i>MikroTik</span><span><i class="map-legend-dot olt"></i>OLT</span><span><i class="map-legend-dot onu"></i>ONU</span><span><i class="map-legend-dot wifi-router"></i>WiFi</span><span><i class="map-legend-dot access-point"></i>AP</span><span><i class="map-legend-dot customer"></i>Customer</span></div></div></div></div>
</div>
<div class="card"><div class="card-body">
<div class="row g-2 mb-3">
<div class="col-lg-3 col-md-6"><label class="form-label">Device / Network Type</label><select id="map-type-filter" class="form-select"><option value="">All Devices</option><option value="router">MikroTik Router</option><option value="olt">OLT</option><option value="onu">ONU</option><option value="wifi-router">WiFi Router</option><option value="access-point">Access Point</option><option value="switch">Switch</option><option value="customer">Customer</option></select></div>
<div class="col-lg-3 col-md-6"><label class="form-label">Router</label><select id="map-router-filter" class="form-select"><option value="">All Routers</option>@foreach($routers as $r)<option value="{{ $r->router_name }}">{{ $r->router_name }}</option>@endforeach</select></div>
<div class="col-lg-2 col-md-6"><label class="form-label">Status</label><select id="map-status-filter" class="form-select"><option value="">All Status</option><option value="online">Online</option><option value="offline">Offline</option><option value="unknown">Unknown</option></select></div>
<div class="col-lg-4 col-md-6"><label class="form-label">Search</label><div class="input-group"><input id="map-customer-search" class="form-control" placeholder="Name, IP, ID or location"><button id="map-fit" class="btn btn-outline-secondary" type="button" title="Fit visible nodes"><i class="bi bi-bounding-box"></i></button><button id="map-reset" class="btn btn-outline-secondary" type="button" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i></button></div></div>
</div>
<div id="network-map" class="network-map-canvas" aria-label="Interactive network map"></div>
<div class="network-map-footer"><span><i class="bi bi-info-circle me-1"></i>Markers are based on stored coordinates. Connections appear only when both endpoints have coordinates.</span><span id="map-result-count"></span></div>
</div></div>
</div>
<script>
(() => {
 const loadLeaflet=()=>new Promise(resolve=>{if(window.L)return resolve();const s=document.createElement('script');s.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';s.onload=resolve;document.head.appendChild(s);});
 window.initNetworkMap=async()=>{await loadLeaflet();const el=document.getElementById('network-map');if(!el||el._map)return;const map=L.map(el,{preferCanvas:true,zoomControl:true}).setView([23.8103,90.4125],11);el._map=map;L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
 const nodes=@json($nodes),edges=@json($mapEdges);let markerLayers=[],lineLayers=[];const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
 const meta={router:['MikroTik Router','router'],olt:['OLT','olt'],onu:['ONU','onu'],'wifi-router':['WiFi Router','wifi-router'],'access-point':['Access Point','access-point'],switch:['Switch','switch'],customer:['Customer','customer']};
 const clear=()=>{markerLayers.forEach(x=>map.removeLayer(x));lineLayers.forEach(x=>map.removeLayer(x));markerLayers=[];lineLayers=[];};
 const fit=pts=>{if(pts.length)map.fitBounds(L.latLngBounds(pts),{padding:[36,36],maxZoom:15});else map.setView([23.8103,90.4125],11);setTimeout(()=>map.invalidateSize(),100);};
 const render=()=>{clear();const tf=document.getElementById('map-type-filter')?.value||'',rf=document.getElementById('map-router-filter')?.value||'',sf=document.getElementById('map-status-filter')?.value||'',q=(document.getElementById('map-customer-search')?.value||'').toLowerCase().trim();const filtered=nodes.filter(n=>(!tf||n.kind===tf)&&(!rf||n.router===rf)&&(!sf||n.status===sf)&&(!q||[n.id,n.label,n.ip,n.location,n.kind].some(v=>String(v??'').toLowerCase().includes(q))));const visibleKeys=new Set(filtered.map(n=>(n.kind==='router'?'router:':'device:')+n.id));
 edges.forEach(e=>{const fromKey=e.fromKey,toKey=e.toKey;if(fromKey&&toKey&&(!visibleKeys.has(fromKey)||!visibleKeys.has(toKey)))return;const l=L.polyline([e.from,e.to],{weight:4,opacity:.65,dashArray:e.type==='logical_service'?'7 7':null}).bindTooltip(esc(e.label));l.addTo(map);lineLayers.push(l);});
 const pts=[];filtered.forEach(n=>{const mta=meta[n.kind]||['Device','device'];const icon=L.divIcon({className:'network-map-marker-wrap',html:'<span class="network-map-marker '+mta[1]+'" title="'+esc(n.label)+'"><i></i></span>',iconSize:[34,34],iconAnchor:[17,17]});const popup='<div class="network-popup"><strong>'+mta[0]+'</strong><div>'+esc(n.label)+'</div><small>IP: '+esc(n.ip||'—')+'</small><small>Status: '+esc(n.status||'unknown')+'</small><small>'+esc(n.location||'')+'</small></div>';const m=L.marker([n.lat,n.lng],{icon}).bindPopup(popup).addTo(map);markerLayers.push(m);pts.push([n.lat,n.lng]);});document.getElementById('map-result-count').textContent=filtered.length+' node'+(filtered.length===1?'':'s')+' shown';fit(pts);};
 ['map-type-filter','map-router-filter','map-status-filter'].forEach(id=>document.getElementById(id)?.addEventListener('change',render));document.getElementById('map-customer-search')?.addEventListener('input',render);document.getElementById('map-fit')?.addEventListener('click',render);document.getElementById('map-reset')?.addEventListener('click',()=>{['map-type-filter','map-router-filter','map-status-filter'].forEach(id=>document.getElementById(id).value='');document.getElementById('map-customer-search').value='';render();});render();};
 document.addEventListener('livewire:navigated',()=>window.initNetworkMap?.());if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>window.initNetworkMap?.());else window.initNetworkMap?.();
})();
</script>
