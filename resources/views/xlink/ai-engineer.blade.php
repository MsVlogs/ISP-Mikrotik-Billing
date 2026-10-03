<x-app-layout>
<style>
.ai-engineer{max-width:1180px;margin:0 auto}.ai-hero{background:linear-gradient(135deg,#172b4d,#276ef1);color:#fff;border-radius:18px;padding:28px;box-shadow:0 12px 30px rgba(31,58,99,.15)}.ai-card{background:#fff;border:1px solid #e5eaf2;border-radius:16px;box-shadow:0 8px 24px rgba(31,58,99,.07)}.ai-chat{min-height:260px}.ai-bubble{background:#f5f8fc;border-radius:14px;padding:16px}.ai-chip{border:1px solid #dbe4f0;background:#fff;border-radius:999px;padding:7px 12px;font-size:13px}.ai-result{border-left:4px solid #276ef1}.ai-result.critical{border-left-color:#dc3545}.ai-result.warning{border-left-color:#f0ad4e}.ai-muted{color:#718096}.ai-actions button{min-width:105px}.ai-readonly{font-size:12px;color:#617083}
</style>
<div class="container-fluid py-4 ai-engineer">
 <div class="ai-hero mb-4"><div class="d-flex align-items-start justify-content-between gap-3"><div><div class="small opacity-75 mb-1">BengalStack</div><h1 class="h3 mb-2">AI Engineer</h1><p class="mb-0 opacity-90">Ask about a customer, a device, billing, or an outage. The assistant explains likely causes and recommended checks — it never changes anything.</p></div><span class="badge bg-light text-primary rounded-pill px-3 py-2">READ-ONLY</span></div></div>
 <div class="row g-3 mb-4">
 @foreach([['label'=>'Customers','value'=>$overview['customers']['total'],'sub'=>$overview['customers']['active'].' active'],['label'=>'Pending / Disabled','value'=>$overview['customers']['pending']+$overview['customers']['disabled'],'sub'=>$overview['customers']['pending'].' pending · '.$overview['customers']['disabled'].' disabled'],['label'=>'Routers','value'=>$overview['routers']['total'],'sub'=>$overview['routers']['connected'].' connected'],['label'=>'OLT/ONU mappings','value'=>$overview['olt_onu']['mapped'],'sub'=>$overview['olt_onu']['mapped'] ? 'mapped customers' : 'no mapping data'],['label'=>'Support tickets','value'=>$overview['support']['tickets'],'sub'=>'recorded tickets']] as $card)
 <div class="col-md-6 col-xl"><div class="ai-card p-3 h-100"><div class="small ai-muted">{{ $card['label'] }}</div><div class="h3 mb-1 mt-1">{{ $card['value'] }}</div><div class="small ai-muted">{{ $card['sub'] }}</div></div></div>
 @endforeach
</div> <div class="row g-3 mb-4">
  @php($n=$overview['network'] ?? [])
  @php($inc=$overview['incidents'] ?? [])
  @foreach([
   ['label'=>'Network Devices','value'=>$n['device_total']??0,'sub'=>($n['olt_total']??0).' OLT'],
   ['label'=>'OLT Health','value'=>($n['olt_online']??0),'sub'=>($n['olt_offline']??0).' offline'],
   ['label'=>'ONU Online','value'=>($n['onu_online']??0),'sub'=>($n['onu_offline']??0).' offline'],
   ['label'=>'Affected Customers','value'=>($n['affected_customers']??0),'sub'=>'mapped ONU offline/LOS'],
   ['label'=>'Open Incidents','value'=>($inc['open']??0),'sub'=>($inc['critical']??0).' critical · '.($inc['warning']??0).' warning'],
   ['label'=>'Unmapped ONU','value'=>($n['unmapped_onu']??0),'sub'=>'needs review'],
  ] as $card)
   <div class="col-6 col-xl-2"><div class="ai-card p-3 h-100"><div class="small ai-muted">{{ $card['label'] }}</div><div class="h3 mb-1 mt-1">{{ $card['value'] }}</div><div class="small ai-muted">{{ $card['sub'] }}</div></div></div>
  @endforeach
 </div>
 <div class="row g-3 mb-4">
  <div class="col-xl-7"><div class="ai-card p-4 h-100">
   <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0"><i class="bi bi-activity me-1"></i> Automatic Network Summary</h2><span class="badge text-bg-{{ ($inc['status']??'healthy')==='critical'?'danger':(($inc['status']??'healthy')==='attention'?'warning':'success') }}">{{ strtoupper($inc['status']??'healthy') }}</span></div>
   <div class="row g-2 small">
    <div class="col-md-6"><div class="border rounded p-2">Routers: <strong>{{ $n['router_online']??0 }}/{{ $n['router_total']??0 }}</strong> connected</div></div>
    <div class="col-md-6"><div class="border rounded p-2">OLT: <strong>{{ $n['olt_online']??0 }}/{{ $n['olt_total']??0 }}</strong> online</div></div>
    <div class="col-md-6"><div class="border rounded p-2">ONU: <strong>{{ $n['onu_online']??0 }}/{{ $n['onu_total']??0 }}</strong> online</div></div>
    <div class="col-md-6"><div class="border rounded p-2">Affected: <strong>{{ $n['affected_customers']??0 }}</strong> customer estimate</div></div>
   </div>
   <div class="mt-3 small ai-muted">Generated {{ $dailySummary['generated_at'] ?? now()->toIso8601String() }} · read-only</div>
  </div></div>
  <div class="col-xl-5"><div class="ai-card p-4 h-100">
   <h2 class="h5 mb-3"><i class="bi bi-exclamation-triangle me-1"></i> Active Incidents</h2>
   @forelse(($inc['latest']??[]) as $event)
    <div class="border rounded p-2 mb-2 small"><div class="fw-semibold">{{ $event['title'] ?? 'Network event' }}</div><div class="ai-muted">{{ $event['severity'] ?? 'warning' }} · {{ $event['status'] ?? 'open' }} · {{ $event['occurrences'] ?? 1 }} occurrence(s)</div></div>
   @empty <div class="ai-muted small">No open network events recorded.</div>
   @endforelse
  </div></div>
 </div>
 <div class="ai-card p-4 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0"><i class="bi bi-diagram-3 me-1"></i> Needs Review · Unmapped ONU</h2><span class="badge text-bg-warning">{{ count($unmappedOnus) }}</span></div>
  @if(count($unmappedOnus))
   <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>OLT</th><th>ONU</th><th>MAC</th><th>PON</th><th>Status</th><th>RX/TX</th><th>Reason</th></tr></thead><tbody>
   @foreach($unmappedOnus as $u)<tr><td>{{ $u['olt'] ?? '—' }}</td><td>{{ $u['onu_id'] ?? '—' }}</td><td>{{ $u['mac'] ?? '—' }}</td><td>{{ $u['pon'] ?? '—' }}</td><td>{{ $u['status'] ?? 'unknown' }}</td><td>{{ $u['rx'] ?? '—' }} / {{ $u['tx'] ?? '—' }}</td><td>{{ $u['reason'] ?? 'Manual review required' }}</td></tr>@endforeach
   </tbody></table></div>
  @else <div class="ai-muted small">No unmapped ONU currently in the review queue.</div>@endif
 </div>

 <div class="row g-3 mb-4">
  <div class="col-xl-7"><div class="ai-card p-4 h-100">
   <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0"><i class="bi bi-diagram-3 me-1"></i> Shared Upstream Impact</h2><span class="badge text-bg-info">{{ count($upstreamCorrelation['groups'] ?? []) }} paths</span></div>
   @forelse(($upstreamCorrelation['groups'] ?? []) as $g)
    <div class="border rounded p-2 mb-2 small">
      <div class="fw-semibold">OLT {{ $g['olt_device_id'] ?? 'unknown' }} · PON {{ $g['pon'] ?? 'unknown' }}</div>
      <div class="ai-muted">{{ $g['affected_onus'] ?? 0 }} affected ONU(s) · {{ $g['affected_customers'] ?? 0 }} affected customer(s) · confidence {{ $g['confidence'] ?? 'low' }}</div>
      <div class="mt-1">{{ implode(', ', $g['statuses'] ?? []) }}</div>
      <div class="ai-muted mt-1">{{ ($g['evidence'][0] ?? 'Shared-path correlation only; root cause is not confirmed.') }}</div>
    </div>
   @empty <div class="ai-muted small">No shared OLT/PON impact group is currently detected.</div>@endforelse
   <div class="ai-readonly mt-2">Correlation is evidence only; AI Engineer does not change service state.</div>
  </div></div>
  <div class="col-xl-5"><div class="ai-card p-4 h-100">
   <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0"><i class="bi bi-person-check me-1"></i> ONU ↔ Customer Candidates</h2><span class="badge text-bg-warning">{{ count($matchingCandidates['matches'] ?? []) }}</span></div>
   @forelse(($matchingCandidates['matches'] ?? []) as $m)
    <div class="border rounded p-2 mb-2 small">
      <div class="fw-semibold">{{ $m['candidate']['customer_id'] ?? '—' }} · {{ $m['candidate']['customer_name'] ?? '—' }}</div>
      <div class="ai-muted">ONU {{ $m['onu_id'] ?? '—' }} · PON {{ $m['pon'] ?? '—' }} · score {{ $m['candidate']['score'] ?? 0 }}/100</div>
      <div class="mt-1">{{ implode(' · ', $m['candidate']['reasons'] ?? []) }}</div>
    </div>
   @empty <div class="ai-muted small">No candidate matches found.</div>@endforelse
   <div class="ai-readonly mt-2">Recommendation only — no automatic mapping or assignment.</div>
  </div></div>
 </div>

 <div class="ai-card p-3 mb-4"><div class="d-flex flex-wrap justify-content-between gap-2"><span><i class="bi bi-database-check me-1"></i> Local diagnostic data is available now.</span><span class="ai-readonly">Conversational AI: {{ ($overview['ai_configured'] ?? $overview['openai_configured']) ? strtoupper($overview['ai_provider'] ?? 'external') . ' configured' : 'local read-only mode' }} · read-only</span></div></div>
<div class="ai-card p-4 mb-4"><div class="row g-3 align-items-end"><div class="col-lg-9"><label class="form-label fw-semibold">Customer</label><input id="aiCustomer" class="form-control form-control-lg" value="{{ $customerId }}" placeholder="Customer ID, name, mobile or PPPoE username" autocomplete="off"><div id="aiSuggestions" class="mt-2 d-flex flex-wrap gap-2"></div></div><div class="col-lg-3"><button id="aiDiagnose" class="btn btn-primary btn-lg w-100"><i class="bi bi-stars me-1"></i> Diagnose</button></div></div><div class="mt-3 d-flex flex-wrap gap-2"><button class="ai-chip js-question" data-q="Why is this customer offline?">Why is this customer offline?</button><button class="ai-chip js-question" data-q="What is wrong with this customer's service path?">Check service path</button><button class="ai-chip js-question" data-q="What billing issue should I check?">Check billing</button></div></div>
 <div class="ai-card p-4 mb-4"><div id="aiChatLog" class="ai-chat mb-3"><div class="ai-bubble"><strong>AI Engineer:</strong> Ask me about this customer, device, billing, or outage.</div></div><div class="input-group"><input id="aiQuestion" class="form-control" placeholder="Why is this customer offline?"><button id="aiAsk" class="btn btn-primary">Ask AI Engineer</button></div></div>
 <div id="aiResult" class="ai-card p-4 ai-chat">@if($diagnosis && ($diagnosis['ok'] ?? false)) @include('xlink.partials.ai-engineer-result',['diagnosis'=>$diagnosis]) @else <div class="text-center py-5 ai-muted"><i class="bi bi-chat-square-text fs-1"></i><h2 class="h5 mt-3">Ask AI Engineer</h2><p class="mb-0">Open a customer and ask a question. No network configuration or customer status will be changed.</p></div>@endif</div>
 <div class="text-center mt-3 ai-readonly"><i class="bi bi-shield-check me-1"></i> Read-only diagnostics. AI Engineer does not provision, reboot, enable, disable, delete, or modify network/customer data.</div>
</div>
<script>
(() => { const input=document.getElementById('aiCustomer'), btn=document.getElementById('aiDiagnose'), result=document.getElementById('aiResult'), suggestions=document.getElementById('aiSuggestions');
 async function askAI(){ const q=document.getElementById('aiQuestion').value.trim(); if(!q)return; const log=document.getElementById('aiChatLog'); const customer=input.value.trim(); log.innerHTML += '<div class="text-end mb-2"><span class="badge text-bg-primary p-2">'+escapeHtml(q)+'</span></div>'; document.getElementById('aiQuestion').value=''; const box=document.createElement('div'); box.className='ai-bubble mb-2'; box.innerHTML='<strong>AI Engineer:</strong> <span class="spinner-border spinner-border-sm"></span> Checking live diagnostic context...'; log.appendChild(box); try{const r=await fetch('{{ route('ai-engineer.chat') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({question:q,customer_id:customer})}); const d=await r.json(); box.innerHTML='<strong>AI Engineer:</strong> '+escapeHtml(d.message||'No response.'); if(!d.configured) box.innerHTML += '<div class="small text-warning mt-2">Conversational AI is not configured yet; local diagnostics remain available.</div>';}catch(e){box.innerHTML='<strong>AI Engineer:</strong> '+escapeHtml(e.message);}}
 document.getElementById('aiAsk').addEventListener('click',askAI); document.getElementById('aiQuestion').addEventListener('keydown',e=>{if(e.key==='Enter')askAI();});
 async function diagnose(){ const id=input.value.trim(); if(!id){input.focus();return;} btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Checking...'; try{const r=await fetch('{{ route('ai-engineer.diagnose') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({customer_id:id})}); const d=await r.json(); if(!d.ok) throw new Error(d.message||'Customer not found'); result.innerHTML=render(d);}catch(e){result.innerHTML='<div class="alert alert-danger mb-0">'+escapeHtml(e.message)+'</div>';}finally{btn.disabled=false;btn.innerHTML='<i class="bi bi-stars me-1"></i> Diagnose';}}
 function render(d){const sev=escapeHtml(d.severity);const p=d.service_path||{};const pathItems=[['Customer',p.customer],['PPPoE',p.pppoe],['Router',p.router],['ONU/OLT',p.onu],['Billing',p.billing]];const pathHtml=pathItems.map(([label,x])=>{x=x||{};const state=x.state||'unknown';const cls=['active','clear'].includes(String(state).toLowerCase())?'success':['disabled','inactive','los','offline','down','due','missing'].includes(String(state).toLowerCase())?'danger':'secondary';const detail=label==='PPPoE'?(x.username||'not linked')+(x.live_session?' · live '+x.live_session.state:''):label==='Router'?(x.name?x.name+' · '+(x.ip||'')+(x.latency_ms!==null&&x.latency_ms!==undefined?' · '+x.latency_ms+' ms':'')+' · check '+(x.check_freshness||'unknown'):'not assigned'):label==='ONU/OLT'?(x.pon?'PON '+x.pon+' · ONU '+(x.onu_id||'')+' · '+(x.olt_name||'OLT')+(x.rx_power!==null&&x.rx_power!==undefined?' · RX '+x.rx_power+' dBm':'')+' · '+(x.olt_state||'unknown'):'not mapped'):label==='Billing'?(x.due>0?'Due '+x.due:'Clear'):state;return '<div class="col-md"><div class="border rounded-3 p-3 h-100"><div class="small text-muted">'+escapeHtml(label)+'</div><div class="fw-semibold mt-1">'+escapeHtml(detail)+'</div><span class="badge text-bg-'+cls+' mt-2">'+escapeHtml(state)+'</span></div></div>';}).join('');return `<div class="ai-result ${sev} ps-3"><div class="d-flex justify-content-between align-items-start"><div><div class="small text-uppercase text-muted">${escapeHtml(d.customer.id)} · ${escapeHtml(d.customer.status)}</div><h2 class="h5 mt-1 mb-2">${escapeHtml(d.summary)}</h2></div><span class="badge text-bg-${sev==='critical'?'danger':sev==='warning'?'warning':'primary'}">${sev}</span></div><h3 class="h6 mt-4">Service path</h3><div class="row g-2 mb-4">${pathHtml}</div><h3 class="h6 mt-4">Likely causes</h3><ul>${d.likely_causes.map(x=>`<li>${escapeHtml(x)}</li>`).join('')}</ul><h3 class="h6 mt-3">Evidence</h3><ul>${(d.evidence.length?d.evidence:['No additional evidence recorded.']).map(x=>`<li>${escapeHtml(x)}</li>`).join('')}</ul><h3 class="h6 mt-3">Recommended checks</h3><ul>${d.recommended_checks.map(x=>`<li>${escapeHtml(x)}</li>`).join('')}</ul><div class="ai-actions d-flex gap-2 mt-4"><button class="btn btn-outline-success btn-sm" onclick="aiFeedback('up')">👍 Helpful</button><button class="btn btn-outline-secondary btn-sm" onclick="aiFeedback('down')">👎 Not helpful</button></div><div class="small text-muted mt-3">Read-only diagnostic · ${escapeHtml(d.generated_at)}</div></div>`;}
 function escapeHtml(s){return String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
 window.aiFeedback=async rating=>{await fetch('{{ route('ai-engineer.feedback') }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({rating,customer_id:input.value.trim()})});}; btn.addEventListener('click',diagnose); input.addEventListener('keydown',e=>{if(e.key==='Enter')diagnose();}); document.querySelectorAll('.js-question').forEach(b=>b.addEventListener('click',()=>{document.getElementById('aiQuestion').value=b.dataset.q;document.getElementById('aiQuestion').focus();}));
 let timer; input.addEventListener('input',()=>{clearTimeout(timer); const q=input.value.trim(); if(q.length<2){suggestions.innerHTML='';return;} timer=setTimeout(async()=>{const r=await fetch('{{ route('ai-engineer.search') }}?q='+encodeURIComponent(q),{headers:{Accept:'application/json'}}); const items=await r.json(); suggestions.innerHTML=items.map(x=>`<button type="button" class="ai-chip" data-id="${escapeHtml(x.customer_unique_id)}">${escapeHtml(x.customer_unique_id)} · ${escapeHtml(x.customer_name||'')}</button>`).join(''); suggestions.querySelectorAll('button').forEach(b=>b.onclick=()=>{input.value=b.dataset.id;suggestions.innerHTML='';diagnose();});},250);});
})();
</script>
</x-app-layout>
