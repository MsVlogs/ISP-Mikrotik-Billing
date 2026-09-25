<?php

namespace App\Services;

use App\Models\CustomersInfo;
use App\Models\OltOnuCustomerMapping;
use App\Models\RouterList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class AiEngineerDiagnosticService
{
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
        $tickets = $this->customerTickets($customer->customer_unique_id);
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
        }
        if ($router) {
            $rs=strtolower((string)($router->action??''));
            $findings[]='Assigned router: '.$router->router_name.' ('.$router->ip_address.') · state '.($router->action?:'unknown');
            if (in_array($rs,['offline','down','disabled'],true)) { $severity='critical'; $causes[]='Assigned MikroTik/router is marked '.$rs.'.'; $checks[]='Check router reachability and monitoring status.'; }
        } elseif ($ppp?->router_name) { $severity=$severity==='critical'?'critical':'warning'; $causes[]='Assigned router "'.$ppp->router_name.'" was not found in router inventory.'; $checks[]='Verify Router List mapping.'; }

        if ($mapping) {
            $findings[]='ONU mapping: PON '.($mapping->pon_port?:'unknown').', ONU '.($mapping->onu_id?:'unknown').', status '.($mapping->status?:'unknown').'.';
            $ms=strtolower((string)$mapping->status);
            if (in_array($ms,['los','offline','down'],true)) { $severity=$ms==='los'?'critical':($severity==='critical'?'critical':'warning'); $causes[]='ONU is reported as '.strtoupper($ms).'.'; $checks[]='Check fiber/ONU power and OLT PON alarms.'; }
        } else $findings[]='No discovered OLT/ONU mapping is currently linked to this customer.';

        if ($billing) {
            $due=(float)($billing->total_due_amount ?? $billing->due_amount ?? 0);
            if ($due>0) { $findings[]='Outstanding billing: '.number_format($due,2); if ((bool)($billing->auto_disable??false)) { $severity=$severity==='critical'?'critical':'warning'; $causes[]='Billing has auto-disable enabled with outstanding dues.'; $checks[]='Review billing due and auto-disable policy.'; } }
        }
        if ($tickets['count']>0) { $findings[]='Support tickets linked: '.$tickets['count'].($tickets['open']!==null?' · open: '.$tickets['open']:''); if (($tickets['open']??0)>0) $checks[]='Review the latest open support ticket before changing service state.'; }
        if (!$causes) { $causes[]='No clear offline cause is recorded in the available billing/network data.'; $checks[]='Check live router session state, last-seen time, and upstream OLT/ONU alarms.'; }

        return ['ok'=>true,'customer'=>['id'=>$customer->customer_unique_id,'name'=>$customer->customer_name,'status'=>$customer->status,'mobile'=>$customer->mobile,'package'=>$customer->package?->package], 'severity'=>$severity,'summary'=>$this->summary($severity,$customer,$causes),'likely_causes'=>array_values(array_unique($causes)),'evidence'=>array_values(array_unique($findings)),'support'=>$tickets,'recommended_checks'=>array_values(array_unique($checks)),'read_only'=>true,'generated_at'=>now()->toIso8601String()];
    }

    public function chat(string $question, ?string $customerId=null, array $history=[]): array
    {
        $diagnosis=$customerId?$this->diagnoseCustomer($customerId):null;
        $apiKey=(string)config('services.openai.api_key');
        if ($apiKey==='') return ['ok'=>false,'configured'=>false,'message'=>'Conversational AI is not configured. Local read-only diagnostics are available.'];
        $context=$diagnosis&&($diagnosis['ok']??false)?json_encode($diagnosis,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):json_encode($this->overview());
        $safeHistory=array_slice(array_map(fn($m)=>['role'=>in_array($m['role']??'', ['user','assistant'],true)?$m['role']:'user','content'=>mb_substr((string)($m['content']??''),0,4000)],$history),-8);
        $input=[['role'=>'developer','content'=>'You are an ISP AI Engineer. READ-ONLY. Never claim to have changed, provisioned, rebooted, enabled, disabled, deleted, or configured anything. Use only supplied context; distinguish evidence from likely causes; never invent live status; answer concisely in the user language.'],['role'=>'user','content'=>'Diagnostic context: '.$context]];
        foreach($safeHistory as $m)$input[]=$m; $input[]=['role'=>'user','content'=>mb_substr($question,0,4000)];
        $response=Http::withToken($apiKey)->acceptJson()->timeout(30)->post('https://api.openai.com/v1/responses',['model'=>config('services.openai.model'),'store'=>false,'input'=>$input]);
        if(!$response->successful()){\Log::error('AI Engineer OpenAI request failed',['status'=>$response->status()]);return ['ok'=>false,'configured'=>true,'message'=>'AI service request failed. Local diagnostics remain available.'];}
        $body=$response->json(); $text=$body['output_text']??''; if($text==='')foreach(($body['output']??[]) as $item)foreach(($item['content']??[]) as $content)if(($content['type']??'')==='output_text')$text.=$content['text']??'';
        return ['ok'=>true,'configured'=>true,'message'=>trim($text),'read_only'=>true];
    }

    public function searchCustomers(string $q): array
    {
        $q=trim($q); if($q==='')return [];
        $query=CustomersInfo::query()->with('pppUser')->search($q);
        $customers=$query->limit(12)->get(['id','customer_unique_id','customer_name','mobile','status']);
        $needle=mb_strtolower($q);
        return $customers->filter(function($c)use($needle){$u=mb_strtolower((string)$c->pppUser?->username);return str_contains($u,$needle)||true;})->map(fn($c)=>['id'=>$c->id,'customer_unique_id'=>$c->customer_unique_id,'customer_name'=>$c->customer_name,'mobile'=>$c->mobile,'status'=>$c->status,'ppp_username'=>$c->pppUser?->username])->values()->all();
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
