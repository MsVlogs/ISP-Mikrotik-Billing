<?php

namespace App\Services;

use App\Models\CustomersInfo;
use App\Models\OltOnuCustomerMapping;
use App\Models\NetworkInventoryDevice;
use App\Models\RouterList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\MikrotikController;
use Illuminate\Support\Facades\Schema;

class AiEngineerDiagnosticService
{
    public function __construct(private ?MikrotikController $mikrotik = null) {}

    public function overview(): array
    {
        $customerCount = CustomersInfo::count();
        $active = CustomersInfo::whereIn(DB::raw('LOWER(status)'), ['active', 'free'])->count();
        $pending = CustomersInfo::whereRaw("LOWER(status) = 'pending'")->count();
        $disabled = CustomersInfo::whereIn(DB::raw('LOWER(status)'), ['disable', 'disabled', 'inactive'])->count();
        $routerTotal = RouterList::count();
        $routerConnected = RouterList::where('action', 'connected')->count();
        $oltMapped = Schema::hasTable('olt_onu_customer_mappings') ? OltOnuCustomerMapping::count() : 0;
        $tickets = Schema::hasTable('support_tickets') ? DB::table('support_tickets')->count() : 0;

        $network = $this->networkSnapshot();
        $incidents = $this->incidentSummary();
        return [
            'customers' => ['total'=>$customerCount,'active'=>$active,'pending'=>$pending,'disabled'=>$disabled],
            'routers' => ['total'=>$routerTotal,'connected'=>$routerConnected,'disconnected'=>max(0,$routerTotal-$routerConnected)],
            'olt_onu' => ['mapped'=>$oltMapped,'total'=>$network['onu_total'],'online'=>$network['onu_online'],'unmapped'=>$network['unmapped_onu']],
            'support' => ['tickets'=>$tickets],
            'network' => $network,
            'incidents' => $incidents,
            'ai_provider' => (string) config('services.ai.provider', 'gemini'),
            'ai_configured' => (string) config('services.'.config('services.ai.provider', 'gemini').'.api_key') !== '',
            'openai_configured' => (string) config('services.openai.api_key') !== '',
            'read_only' => true,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function diagnoseCustomer(string $customerId): array
    {
        $customer = CustomersInfo::with(['pppUser', 'package', 'billing'])
            ->where('customer_unique_id', $customerId)->first();
        if (! $customer) return ['ok'=>false,'message'=>'Customer not found.'];

        $ppp = $customer->pppUser;
        $billing = $customer->billing;
        $mapping = Schema::hasTable('olt_onu_customer_mappings')
            ? OltOnuCustomerMapping::where('customer_id', $customer->id)->latest('id')->first() : null;
        $router = $ppp?->router_name ? RouterList::where('router_name', $ppp->router_name)->first() : null;
        $livePpp = $this->readLivePppSession($router, $ppp?->username);
        $routerAge = $router?->last_checked_at ? max(0, now()->diffInSeconds($router->last_checked_at, false) * -1) : null;
        $olt = $mapping ? NetworkInventoryDevice::find($mapping->olt_device_id) : null;
        $oltHealth = $olt ? $olt->healthChecks()->latest('checked_at')->first() : null;
        $tickets = $this->customerTickets($customer->customer_unique_id);
        $path = [
            'customer' => ['status'=>$customer->status, 'state'=>$this->stateForCustomer($customer->status)],
            'pppoe' => $ppp ? [
                'username'=>$ppp->username,
                'status'=>$ppp->status,
                'state'=>$this->stateForPpp($ppp->status),
                'router'=>$ppp->router_name,
                'remote_ip'=>$ppp->ppp_remote_ip,
                'uptime'=>$ppp->uptime,
                'downtime'=>$ppp->downtime,
                'last_logged_out'=>$ppp->last_logged_out,
                'last_disconnect_reason'=>$ppp->last_disconnect_reason,
                'live_session'=>$livePpp,
            ] : ['state'=>'missing'],
            'router' => $router ? ['name'=>$router->router_name,'ip'=>$router->ip_address,'state'=>$router->action ?: 'unknown','latency_ms'=>$router->last_latency_ms,'last_checked_at'=>$router->last_checked_at,'check_age_seconds'=>$routerAge,'check_freshness'=>$routerAge === null ? 'unknown' : ($routerAge <= 900 ? 'fresh' : 'stale')] : ['state'=>$ppp?->router_name ? 'missing' : 'not_assigned'],
            'onu' => $mapping ? ['olt_device_id'=>$mapping->olt_device_id,'olt_name'=>$olt?->name,'olt_ip'=>$olt?->ip_address,'olt_state'=>$olt?->health_status ?: $olt?->status ?: 'unknown','olt_latency_ms'=>$olt?->last_latency_ms,'olt_last_checked_at'=>$olt?->last_checked_at,'health_check'=>$oltHealth ? ['status'=>$oltHealth->status,'latency_ms'=>$oltHealth->latency_ms,'checked_at'=>$oltHealth->checked_at] : null,'pon'=>$mapping->pon_port,'onu_id'=>$mapping->onu_id,'serial'=>$mapping->onu_serial,'mac'=>$mapping->onu_mac,'state'=>$mapping->status ?: 'unknown','rx_power'=>$mapping->rx_power,'tx_power'=>$mapping->tx_power,'ip'=>$mapping->onu_ip,'last_seen_at'=>$mapping->last_seen_at,'last_seen_age_seconds'=>$mapping->last_seen_at ? max(0, now()->diffInSeconds($mapping->last_seen_at, false) * -1) : null] : ['state'=>'not_mapped'],
            'billing' => $billing ? ['state'=>$this->billingState($billing),'due'=>$this->billingDue($billing)] : ['state'=>'not_found'],
        ];
        $findings=[]; $causes=[]; $checks=[]; $severity='info';

        $status = strtolower((string)$customer->status);
        if (in_array($status,['disable','disabled','inactive'],true)) { $severity='critical'; $causes[]='Customer account is disabled/inactive in billing.'; $checks[]='Review customer status before restoring service.'; }
        elseif ($status==='pending') { $severity='warning'; $causes[]='Customer is still in Pending state.'; $checks[]='Verify service activation workflow and router status.'; }

        if (! $ppp) { $severity='critical'; $causes[]='No PPP user is linked to this customer.'; $checks[]='Verify PPPoE assignment.'; }
        else {
            if (! $ppp->username) { $severity='critical'; $causes[]='PPP username is empty.'; }
            if (strtolower((string)$ppp->status)!=='active') { $severity=$severity==='critical'?'critical':'warning'; $causes[]='Linked PPP secret is not active.'; $checks[]='Verify PPP secret state on the assigned router.'; }
            if ($ppp->last_disconnect_reason) $findings[]='Last disconnect reason: '.$ppp->last_disconnect_reason;
            if ($ppp->last_logged_out) $findings[]='Last logged out: '.$ppp->last_logged_out;
            if ($ppp->username) $findings[]='PPPoE username: '.$ppp->username;
            if ($ppp->ppp_remote_ip) $findings[]='PPP remote IP: '.$ppp->ppp_remote_ip;
            if ($ppp->uptime) $findings[]='PPP uptime recorded: '.$ppp->uptime;
            if ($ppp->last_disconnect_reason) $findings[]='Disconnect evidence: '.$ppp->last_disconnect_reason;
            if (($livePpp['state'] ?? null) === 'online') {
                $findings[]='Live MikroTik PPPoE session found for this username.';
            } elseif (($livePpp['state'] ?? null) === 'offline') {
                $findings[]='No live MikroTik PPPoE session found for this username.';
                if (strtolower((string)$ppp->status) === 'active') {
                    $severity=$severity==='critical'?'critical':'warning';
                    $causes[]='PPP secret is active in billing data, but no live PPPoE session was found on the assigned router.';
                    $checks[]='Check router logs, PPPoE authentication errors, and customer CPE reachability.';
                }
            }
        }
        if ($router) {
            $rs=strtolower((string)($router->action??''));
            $findings[]='Assigned router: '.$router->router_name.' ('.$router->ip_address.') · state '.($router->action?:'unknown');
            if ($router->last_latency_ms !== null) $findings[]='Last recorded router latency: '.$router->last_latency_ms.' ms.';
            if ($router->last_checked_at) $findings[]='Router health last checked: '.$router->last_checked_at.' ('.(($routerAge ?? 0) > 900 ? 'stale, over 15 minutes old' : 'within 15 minutes').').';
            else $findings[]='Router health has no recorded last-check timestamp.';
            if (in_array($rs,['offline','down','disabled'],true)) { $severity='critical'; $causes[]='Assigned MikroTik/router is marked '.$rs.'.'; $checks[]='Check router reachability and monitoring status.'; }
        } elseif ($ppp?->router_name) { $severity=$severity==='critical'?'critical':'warning'; $causes[]='Assigned router "'.$ppp->router_name.'" was not found in router inventory.'; $checks[]='Verify Router List mapping.'; }

        if ($mapping) {
            $findings[]='ONU mapping: PON '.($mapping->pon_port?:'unknown').', ONU '.($mapping->onu_id?:'unknown').', status '.($mapping->status?:'unknown').'.';
            if ($mapping->rx_power !== null) $findings[]='ONU RX power: '.$mapping->rx_power.' dBm (interpret against this OLT/ONU vendor thresholds).';
            if ($mapping->tx_power !== null) $findings[]='ONU TX power: '.$mapping->tx_power.' dBm.';
            if ($mapping->last_seen_at) $findings[]='ONU last seen: '.$mapping->last_seen_at.'.';
            else $findings[]='ONU mapping has no last-seen timestamp.';
            if ($olt) {
                $findings[]='OLT '.$olt->name.' ('.$olt->ip_address.') health state: '.($olt->health_status ?: $olt->status ?: 'unknown').'.';
                if ($oltHealth) $findings[]='Latest OLT health check: '.$oltHealth->status.($oltHealth->latency_ms !== null ? ' · '.$oltHealth->latency_ms.' ms' : '').' at '.$oltHealth->checked_at.'.';
                $os = strtolower((string)($olt->health_status ?: $olt->status ?: 'unknown'));
                if (in_array($os, ['offline','down','critical','unreachable'], true)) {
                    $severity = $os === 'critical' ? 'critical' : ($severity === 'critical' ? 'critical' : 'warning');
                    $causes[] = 'Linked OLT inventory is marked '.$os.'.';
                    $checks[] = 'Verify OLT reachability and review recent OLT/PON alarms; no provisioning action was taken.';
                }
            }
            $ms=strtolower((string)$mapping->status);
            if (in_array($ms,['los','offline','down'],true)) { $severity=$ms==='los'?'critical':($severity==='critical'?'critical':'warning'); $causes[]='ONU is reported as '.strtoupper($ms).'.'; $checks[]='Check fiber/ONU power and OLT PON alarms.'; }
        } else $findings[]='No discovered OLT/ONU mapping is currently linked to this customer.';

        if ($billing) {
            $due=(float)($billing->total_due_amount ?? $billing->due_amount ?? 0);
            if ($due>0) { $findings[]='Outstanding billing: '.number_format($due,2); if ((bool)($billing->auto_disable??false)) { $severity=$severity==='critical'?'critical':'warning'; $causes[]='Billing has auto-disable enabled with outstanding dues.'; $checks[]='Review billing due and auto-disable policy.'; } }
        }
        if ($tickets['count']>0) { $findings[]='Support tickets linked: '.$tickets['count'].($tickets['open']!==null?' · open: '.$tickets['open']:''); if (($tickets['open']??0)>0) $checks[]='Review the latest open support ticket before changing service state.'; }
        if (!$causes) { $causes[]='No clear offline cause is recorded in the available billing/network data.'; $checks[]='Check live router session state, last-seen time, and upstream OLT/ONU alarms.'; }

        return ['ok'=>true,'customer'=>['id'=>$customer->customer_unique_id,'name'=>$customer->customer_name,'status'=>$customer->status,'mobile'=>$customer->mobile,'package'=>$customer->package?->package], 'severity'=>$severity,'summary'=>$this->summary($severity,$customer,$causes),'likely_causes'=>array_values(array_unique($causes)),'evidence'=>array_values(array_unique($findings)),'support'=>$tickets,'recommended_checks'=>array_values(array_unique($checks)),'service_path'=>$path,'read_only'=>true,'generated_at'=>now()->toIso8601String()];
    }

    private function stateForCustomer(?string $status): string
    {
        $s=strtolower((string)$status);
        return in_array($s,['active','free'],true)?'active':(in_array($s,['disable','disabled','inactive'],true)?'disabled':($s==='pending'?'pending':'unknown'));
    }

    private function stateForPpp(?string $status): string
    {
        $s=strtolower((string)$status);
        return $s==='active'?'active':($s===''?'unknown':'inactive');
    }

    private function billingDue($billing): float
    {
        return (float)($billing->total_due_amount ?? $billing->due_amount ?? 0);
    }

    private function billingState($billing): string
    {
        $due=$this->billingDue($billing);
        return $due>0?'due':'clear';
    }

    public function chat(string $question, ?string $customerId=null, array $history=[]): array
    {
        $diagnosis=$customerId?$this->diagnoseCustomer($customerId):null;
        $provider=strtolower((string)config('services.ai.provider','gemini'));
        if(!in_array($provider,['gemini','openai'],true))$provider='gemini';
        $apiKey=(string)config("services.{$provider}.api_key");
        $contextData=$diagnosis&&($diagnosis['ok']??false)?$diagnosis:$this->overview();
        if(!$diagnosis){
            $contextData['network_snapshot']=$this->networkSnapshot();
            $contextData['incident_summary']=$this->incidentSummary();
            $contextData['unmapped_onus']=$this->unmappedOnus(12);
            $contextData['optical_power']=$this->opticalPowerList(50);
        }
        $context=json_encode($contextData,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        // Local diagnostics remain available if the selected provider has no key.
        if ($apiKey==='') {
            return ['ok'=>true,'configured'=>true,'provider'=>'local','message'=>$this->localChatResponse($question,$diagnosis),'read_only'=>true];
        }
        $safeHistory=array_slice(array_map(fn($m)=>['role'=>in_array($m['role']??'', ['user','assistant'],true)?$m['role']:'user','content'=>mb_substr((string)($m['content']??''),0,4000)],$history),-8);
        $systemPrompt='You are an ISP AI Engineer. READ-ONLY. Never claim to have changed, provisioned, rebooted, enabled, disabled, deleted, or configured anything. Use only supplied context; distinguish evidence from likely causes; never invent live status; answer concisely in the user language.';
        if($provider==='gemini') {
            $messages=[['role'=>'system','content'=>$systemPrompt],['role'=>'user','content'=>'Diagnostic context: '.$context]];
            foreach($safeHistory as $m)$messages[]=$m;
            $messages[]=['role'=>'user','content'=>mb_substr($question,0,4000)];
            $baseUrl=rtrim((string)config('services.gemini.base_url','https://generativelanguage.googleapis.com/v1beta/openai'),'/');
            $payload=['model'=>config('services.gemini.model','gemini-3.8-flash'),'messages'=>$messages,'reasoning_effort'=>'low'];
            try {
                $response=Http::withToken($apiKey)->acceptJson()->timeout(30)->retry(2,500,fn($exception,$request)=>true,false)->post($baseUrl.'/chat/completions',$payload);
            } catch (\Throwable $e) {
                \Log::error('AI Engineer Gemini request exception',['error'=>$e->getMessage()]);
                return ['ok'=>true,'configured'=>true,'provider'=>'local','message'=>$this->localChatResponse($question,$diagnosis),'read_only'=>true,'fallback'=>true];
            }
            if(!$response->successful()){
                \Log::error('AI Engineer Gemini request failed',['status'=>$response->status(),'body'=>mb_substr($response->body(),0,1000)]);
                return ['ok'=>true,'configured'=>true,'provider'=>'local','message'=>$this->localChatResponse($question,$diagnosis),'read_only'=>true,'fallback'=>true];
            }
            $body=$response->json();
            $text=$body['choices'][0]['message']['content']??'';
            if(is_array($text))$text=implode("\n",array_map(fn($part)=>(string)($part['text']??''),$text));
            return ['ok'=>true,'configured'=>true,'provider'=>'gemini','message'=>trim((string)$text),'read_only'=>true];
        }

        $input=[['role'=>'developer','content'=>$systemPrompt],['role'=>'user','content'=>'Diagnostic context: '.$context]];
        foreach($safeHistory as $m)$input[]=$m;
        $input[]=['role'=>'user','content'=>mb_substr($question,0,4000)];
        $response=Http::withToken($apiKey)->acceptJson()->timeout(30)->post('https://api.openai.com/v1/responses',['model'=>config('services.openai.model'),'store'=>false,'input'=>$input]);
        if(!$response->successful()){\Log::error('AI Engineer OpenAI request failed',['status'=>$response->status()]);return ['ok'=>false,'configured'=>true,'provider'=>'openai','message'=>'OpenAI request failed. Local diagnostics remain available.'];}
        $body=$response->json(); $text=$body['output_text']??'';
        if($text==='')foreach(($body['output']??[]) as $item)foreach(($item['content']??[]) as $content)if(($content['type']??'')==='output_text')$text.=$content['text']??'';
        return ['ok'=>true,'configured'=>true,'provider'=>'openai','message'=>trim($text),'read_only'=>true];
    }

    private function localChatResponse(string $question, ?array $diagnosis): string
    {
        $q=mb_strtolower(trim($question));
        if (!$diagnosis || !($diagnosis['ok']??false)) {
            if (str_contains($q,'optical') || str_contains($q,'power') || str_contains($q,'rx') || str_contains($q,'tx') || str_contains($q,'অপ্টিকাল') || str_contains($q,'পাওয়ার')) {
                $rows=$this->opticalPowerList(50);
                if (!$rows) return 'No ONU optical-power readings are available in the current mapping data.';
                $parts=array_map(fn($r)=>($r['olt']?:'OLT').' | ONU '.($r['onu_id']??'—').' | MAC '.($r['mac']??'—').' | PON '.($r['pon']??'—').' | RX '.($r['rx']??'—').' dBm | TX '.($r['tx']??'—').' dBm | '.($r['status']??'unknown'),$rows);
                return 'বর্তমান ONU optical power তালিকা:\n'.implode("\n",$parts);
            }
            if (str_contains($q,'billing') || str_contains($q,'bill') || str_contains($q,'due')) return 'For billing, check the customer\'s outstanding amount, auto-disable setting, account status, and any open support ticket. Select a customer to get customer-specific evidence.';
            if (str_contains($q,'device') || str_contains($q,'router') || str_contains($q,'mikrotik')) return 'For a device issue, check the assigned router inventory state, last-seen/session information, and any linked ONU mapping. Select a customer for a service-path diagnosis.';
            return 'Local read-only diagnostics are active. Select a customer and ask about service path, billing, PPPoE, router, ONU, or outage symptoms.';
        }

        $c=$diagnosis['customer']??[];
        $causes=$diagnosis['likely_causes']??[];
        $evidence=$diagnosis['evidence']??[];
        $checks=$diagnosis['recommended_checks']??[];
        $billingQuestion=str_contains($q,'billing')||str_contains($q,'bill')||str_contains($q,'due')||str_contains($q,'payment');
        $pathQuestion=str_contains($q,'service path')||str_contains($q,'offline')||str_contains($q,'outage')||str_contains($q,'connection')||str_contains($q,'internet');
        $pppQuestion=str_contains($q,'pppoe')||str_contains($q,'ppp');
        $routerQuestion=str_contains($q,'router')||str_contains($q,'mikrotik');
        $onuQuestion=str_contains($q,'onu')||str_contains($q,'olt')||str_contains($q,'fiber');

        if ($billingQuestion) {
            $items=array_values(array_filter($evidence,fn($x)=>str_contains(mb_strtolower($x),'billing')||str_contains(mb_strtolower($x),'due')||str_contains(mb_strtolower($x),'ticket')));
            $answer=$items ? implode(' ',$items) : 'No outstanding billing amount is recorded in the available customer data.';
            if ($diagnosis['severity']==='critical' || $diagnosis['severity']==='warning') $answer.=' Also review: '.implode(' ',$checks);
            return 'Billing check for '.($c['id']??'customer').': '.$answer;
        }

        if ($pppQuestion) $focus=array_values(array_filter($causes,fn($x)=>str_contains(mb_strtolower($x),'ppp')));
        elseif ($routerQuestion) $focus=array_values(array_filter($causes,fn($x)=>str_contains(mb_strtolower($x),'router')));
        elseif ($onuQuestion) $focus=array_values(array_filter($causes,fn($x)=>str_contains(mb_strtolower($x),'onu')));
        else $focus=[];

        if ($pathQuestion || $pppQuestion || $routerQuestion || $onuQuestion) {
            $answer=$focus ?: $causes;
            $text=implode(' ',$answer);
            if ($text==='') $text='No specific fault is recorded in the available data.';
            return 'Service-path analysis for '.($c['id']??'customer').': '.$text.' Recommended checks: '.implode(' ',$checks);
        }

        return 'For '.($c['id']??'this customer').', current severity is '.($diagnosis['severity']??'unknown').'. '.($diagnosis['summary']??'No summary available.').' Evidence: '.implode(' ',$evidence). ' Recommended checks: '.implode(' ',$checks);
    }

    public function networkSnapshot(): array
    {
        $devices=NetworkInventoryDevice::whereNotNull('type')->get(['id','type','status','health_status','onu_total','onu_online']);
        $olts=$devices->filter(fn($d)=>in_array(strtolower((string)$d->type),['olt','epon','gpon'],true));
        $oltOnline=0; $oltOffline=0; $onuTotal=0; $onuOnline=0; $liveRead=0;
        foreach($olts as $olt){
            try {
                $live=app(\App\Services\Olt\OltReadOnlyAdapterManager::class)->read($olt);
                if(($live['ok']??false)===true){
                    $liveRead++; $oltOnline++; $onus=$live['onus']??[];
                    $onuTotal+=count($onus);
                    $onuOnline+=count(array_filter($onus,fn($o)=>strtolower((string)($o['status']??''))==='online'));
                    continue;
                }
            } catch (\Throwable $e) { \Log::debug('AI Engineer OLT live summary failed',['device_id'=>$olt->id,'error'=>$e->getMessage()]); }
            $state=strtolower((string)($olt->health_status?:$olt->status));
            if(in_array($state,['online','up','connected','healthy'],true)) $oltOnline++;
            elseif(in_array($state,['offline','down','unreachable','critical'],true)) $oltOffline++;
            $onuTotal+=(int)($olt->onu_total??0); $onuOnline+=(int)($olt->onu_online??0);
        }
        $mappingTotal=Schema::hasTable('olt_onu_customer_mappings')?OltOnuCustomerMapping::count():0;
        $mapped=Schema::hasTable('olt_onu_customer_mappings')?OltOnuCustomerMapping::whereNotNull('customer_id')->count():0;
        $routers=RouterList::get(['action']);
        return ['device_total'=>$devices->count(),'olt_total'=>$olts->count(),'olt_online'=>$oltOnline,'olt_offline'=>$oltOffline,
            'router_total'=>$routers->count(),'router_online'=>$routers->where('action','connected')->count(),
            'onu_total'=>$onuTotal,'onu_online'=>$onuOnline,'onu_offline'=>max(0,$onuTotal-$onuOnline),
            'mapping_total'=>$mappingTotal,'mapped_customers'=>$mapped,'unmapped_onu'=>max(0,$mappingTotal-$mapped),
            'affected_customers'=>Schema::hasTable('olt_onu_customer_mappings')?OltOnuCustomerMapping::whereNotNull('customer_id')->whereIn(DB::raw('LOWER(status)'),['offline','down','los'])->distinct('customer_id')->count('customer_id'):0,
            'live_olt_reads'=>$liveRead,'generated_at'=>now()->toIso8601String()];
    }

    public function incidentSummary(): array
    {
        $e=['total'=>0,'open'=>0,'critical'=>0,'warning'=>0,'latest'=>[]];
        if(Schema::hasTable('network_events')){
            $q=DB::table('network_events'); $open=(clone $q)->whereNotIn(DB::raw('LOWER(status)'),['resolved','closed']);
            $e['total']=(clone $q)->count(); $e['open']=(clone $open)->count();
            $e['critical']=(clone $open)->whereRaw("LOWER(severity)='critical'")->count();
            $e['warning']=(clone $open)->whereRaw("LOWER(severity)='warning'")->count();
            $e['latest']=(clone $open)->latest('id')->limit(8)->get(['id','device_id','severity','title','message','status','occurrences','last_seen_at'])->map(fn($x)=>(array)$x)->values()->all();
        }
        $n=$this->networkSnapshot(); $e['affected_customers']=$n['affected_customers'];
        $e['status']=$e['critical']>0?'critical':($e['open']>0||$n['olt_offline']>0?'attention':'healthy'); return $e;
    }

    public function unmappedOnus(int $limit=20): array
    {
        if(!Schema::hasTable('olt_onu_customer_mappings')) return [];
        return OltOnuCustomerMapping::with('olt')->whereNull('customer_id')->latest('id')->limit($limit)->get()->map(fn($m)=>[
            'id'=>$m->id,'olt'=>$m->olt?->name,'onu_id'=>$m->onu_id,'mac'=>$m->onu_mac,'serial'=>$m->onu_serial,'pon'=>$m->pon_port,
            'status'=>$m->status,'rx'=>$m->rx_power,'tx'=>$m->tx_power,'last_seen'=>$m->last_seen_at,'reason'=>$m->notes?:'No exact customer/PPPoE match found.'
        ])->values()->all();
    }

    public function opticalPowerList(int $limit=50): array
    {
        if(!Schema::hasTable('olt_onu_customer_mappings')) return [];
        return OltOnuCustomerMapping::with('olt')->latest('id')->limit($limit)->get()->map(fn($m)=>[
            'id'=>$m->id,'olt'=>$m->olt?->name,'onu_id'=>$m->onu_id,'mac'=>$m->onu_mac,'serial'=>$m->onu_serial,
            'pon'=>$m->pon_port,'status'=>$m->status,'rx'=>$m->rx_power,'tx'=>$m->tx_power,'last_seen'=>$m->last_seen_at,
        ])->values()->all();
    }

    public function dailySummary(): array
    {
        $o=$this->overview(); return ['status'=>$o['incidents']['status'],'network'=>$o['network'],'incidents'=>$o['incidents'],
            'customers'=>$o['customers'],'routers'=>$o['routers'],'olt_onu'=>$o['olt_onu'],'generated_at'=>now()->toIso8601String(),'read_only'=>true];
    }

    public function searchCustomers(string $q): array
    {
        $q=trim($q); if($q==='')return [];
        $customers = CustomersInfo::query()
            ->with('pppUser')
            ->search($q)
            ->limit(12)
            ->get(['id', 'customer_unique_id', 'customer_name', 'mobile', 'status', 'ppp_user_id']);

        return $customers->map(fn ($customer) => [
            'id' => $customer->id,
            'customer_unique_id' => $customer->customer_unique_id,
            'customer_name' => $customer->customer_name,
            'mobile' => $customer->mobile,
            'status' => $customer->status,
            'ppp_username' => $customer->pppUser?->username,
        ])->values()->all();
    }


    private function readLivePppSession(?RouterList $router, ?string $username): array
    {
        if (! $router || ! $username || strtolower((string)$router->action) !== 'connected') {
            return ['state'=>'not_checked','reason'=>'router_not_connected'];
        }
        try {
            $controller = $this->mikrotik ?: app(MikrotikController::class);
            $rows = $controller->singleRead(
                $router->router_name,
                '/ppp/active/print',
                '/ppp active print without-paging terse',
                [],
                false,
                true
            );
            foreach ($rows as $row) {
                $name = $row['name'] ?? $row['user'] ?? $row['username'] ?? null;
                if ((string)$name === (string)$username) {
                    return [
                        'state'=>'online',
                        'address'=>$row['address'] ?? $row['remote-address'] ?? null,
                        'caller_id'=>$row['caller-id'] ?? $row['caller_id'] ?? null,
                        'uptime'=>$row['uptime'] ?? null,
                        'service'=>$row['service'] ?? null,
                    ];
                }
            }
            return ['state'=>'offline','checked_at'=>now()->toIso8601String()];
        } catch (\Throwable $e) {
            \Log::debug('AI Engineer live PPP read failed', ['router'=>$router->router_name,'error'=>$e->getMessage()]);
            return ['state'=>'not_checked','reason'=>'router_read_failed'];
        }
    }

    private function customerTickets(string $id): array
    {
        if(!Schema::hasTable('support_tickets')||!Schema::hasColumn('support_tickets','customer_unique_id'))return ['count'=>0,'open'=>null,'latest'=>null];
        $q=DB::table('support_tickets')->where('customer_unique_id',$id); $count=(clone $q)->count(); $open=null;
        if(Schema::hasColumn('support_tickets','status'))$open=(clone $q)->whereNotIn(DB::raw('LOWER(status)'),['closed','resolved'])->count();
        $latest=(clone $q)->latest('id')->first();
        return ['count'=>$count,'open'=>$open,'latest'=>$latest?['id'=>$latest->id,'subject'=>$latest->subject??null,'status'=>$latest->status??null]:null];
    }
    private function summary(string $severity,CustomersInfo $customer,array $causes):string{return (match($severity){'critical'=>'High-priority issue found.','warning'=>'Potential service issue found.',default=>'No critical issue is visible from the available data.'}).' '.$customer->customer_unique_id.' — '.$causes[0];}
}
