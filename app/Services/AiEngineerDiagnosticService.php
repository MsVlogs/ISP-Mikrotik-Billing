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

        return [
            'customers' => ['total'=>$customerCount,'active'=>$active,'pending'=>$pending,'disabled'=>$disabled],
            'routers' => ['total'=>$routerTotal,'connected'=>$routerConnected,'disconnected'=>max(0,$routerTotal-$routerConnected)],
            'olt_onu' => ['mapped'=>$oltMapped],
            'support' => ['tickets'=>$tickets],
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
        $apiKey=(string)config('services.openai.api_key');
        $context=$diagnosis&&($diagnosis['ok']??false)?json_encode($diagnosis,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):json_encode($this->overview());

        // Keep the conversational assistant useful even when no external AI key is configured.
        // This local mode is deliberately read-only and answers from the same verified diagnostics.
        if ($apiKey==='') {
            return ['ok'=>true,'configured'=>true,'provider'=>'local','message'=>$this->localChatResponse($question,$diagnosis),'read_only'=>true];
        }
        $safeHistory=array_slice(array_map(fn($m)=>['role'=>in_array($m['role']??'', ['user','assistant'],true)?$m['role']:'user','content'=>mb_substr((string)($m['content']??''),0,4000)],$history),-8);
        $input=[['role'=>'developer','content'=>'You are an ISP AI Engineer. READ-ONLY. Never claim to have changed, provisioned, rebooted, enabled, disabled, deleted, or configured anything. Use only supplied context; distinguish evidence from likely causes; never invent live status; answer concisely in the user language.'],['role'=>'user','content'=>'Diagnostic context: '.$context]];
        foreach($safeHistory as $m)$input[]=$m; $input[]=['role'=>'user','content'=>mb_substr($question,0,4000)];
        $response=Http::withToken($apiKey)->acceptJson()->timeout(30)->post('https://api.openai.com/v1/responses',['model'=>config('services.openai.model'),'store'=>false,'input'=>$input]);
        if(!$response->successful()){\Log::error('AI Engineer OpenAI request failed',['status'=>$response->status()]);return ['ok'=>false,'configured'=>true,'message'=>'AI service request failed. Local diagnostics remain available.'];}
        $body=$response->json(); $text=$body['output_text']??''; if($text==='')foreach(($body['output']??[]) as $item)foreach(($item['content']??[]) as $content)if(($content['type']??'')==='output_text')$text.=$content['text']??'';
        return ['ok'=>true,'configured'=>true,'message'=>trim($text),'read_only'=>true];
    }

    private function localChatResponse(string $question, ?array $diagnosis): string
    {
        $q=mb_strtolower(trim($question));
        if (!$diagnosis || !($diagnosis['ok']??false)) {
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

    public function searchCustomers(string $q): array
    {
        $q=trim($q); if($q==='')return [];
        $query=CustomersInfo::query()->with('pppUser')->search($q);
        $customers=$query->limit(12)->get(['id','customer_unique_id','customer_name','mobile','status']);
        $needle=mb_strtolower($q);
        return $customers->filter(function($c)use($needle){$u=mb_strtolower((string)$c->pppUser?->username);return str_contains($u,$needle)||true;})->map(fn($c)=>['id'=>$c->id,'customer_unique_id'=>$c->customer_unique_id,'customer_name'=>$c->customer_name,'mobile'=>$c->mobile,'status'=>$c->status,'ppp_username'=>$c->pppUser?->username])->values()->all();
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
