<?php

namespace App\Http\Controllers;

use App\Models\CustomersInfo;
use App\Models\KycRequest;
use App\Models\NotificationLogs;
use App\Models\PackageList;
use App\Models\SalesQuery;
use App\Models\SupportTicket;
use App\Models\SupportTicketTemplate;
use App\Models\User;
use Illuminate\Http\Request;

class SupportCenterController extends Controller
{
    private function authorizeSupport(): void
    {
        abort_unless(hasAccess(["Super Admin"], ["manage-tickets", "view-tickets"]), 403, "Unauthorized action.");
    }

    private function staff()
    {
        return User::query()->orderBy("name")->get(["id", "name"]);
    }

    public function dashboard(Request $request)
    {
        $this->authorizeSupport();
        $q = SupportTicket::query();
        if ($request->filled("status")) $q->where("status", $request->string("status"));
        if ($request->filled("type")) $q->where("ticket_type", $request->string("type"));
        if ($request->filled("q")) {
            $term = $request->string("q");
            $q->where(fn($x) => $x->where("ticket_no", "like", "%{$term}%")->orWhere("subject", "like", "%{$term}%")->orWhere("customer_unique_id", "like", "%{$term}%"));
        }
        if ($request->filled("date_from")) $q->whereDate("created_at", ">=", $request->date_from);
        if ($request->filled("date_to")) $q->whereDate("created_at", "<=", $request->date_to);

        $tickets = (clone $q)->with(["customer", "assignee"])->latest()->limit(12)->get();
        $stats = [
            "total" => SupportTicket::count(),
            "new" => SupportTicket::where("status", "new")->count(),
            "open" => SupportTicket::whereIn("status", ["open", "pending", "in_progress"])->count(),
            "closed" => SupportTicket::whereIn("status", ["closed", "resolved"])->count(),
            "complain" => SupportTicket::where("ticket_type", "complain")->count(),
            "task" => SupportTicket::where("ticket_type", "task")->count(),
            "sales" => SupportTicket::where("ticket_type", "sales")->count(),
            "kyc" => KycRequest::where("status", "pending")->count(),
        ];
        return view("xlink.support-center", compact("tickets", "stats"));
    }

    public function tickets(Request $request)
    {
        $this->authorizeSupport();
        $query = SupportTicket::with(["customer", "assignee"])->latest();
        foreach (["status", "ticket_type", "assigned_to"] as $filter) if ($request->filled($filter)) $query->where($filter, $request->input($filter));
        if ($request->filled("q")) { $term=$request->string("q"); $query->where(fn($q)=>$q->where("ticket_no","like","%{$term}%")->orWhere("subject","like","%{$term}%")->orWhere("customer_unique_id","like","%{$term}%")->orWhere("ppp_username","like","%{$term}%")); }
        if ($request->filled("date_from")) $query->whereDate("created_at",">=",$request->date_from);
        if ($request->filled("date_to")) $query->whereDate("created_at","<=",$request->date_to);
        return view("xlink.support-center-tickets", ["tickets"=>$query->paginate(25)->withQueryString(), "staff"=>$this->staff()]);
    }

    public function createTicket()
    {
        $this->authorizeSupport();
        return view("xlink.support-center-ticket-create", ["customers"=>CustomersInfo::query()->orderBy("customer_name")->limit(300)->get(), "staff"=>$this->staff(), "templates"=>SupportTicketTemplate::where("active",true)->orderBy("sort_order")->get()]);
    }

    public function storeTicket(Request $request)
    {
        $this->authorizeSupport();
        $data=$request->validate([
            "customer_unique_id"=>["required","exists:customers_infos,customer_unique_id"],
            "ticket_type"=>["required","in:complain,task,sales"],
            "priority"=>["required","in:low,medium,high,urgent"],
            "topic"=>["nullable","string","max:120"], "assigned_to"=>["nullable","exists:users,id"],
            "subject"=>["required","string","max:190"], "description"=>["required","string","max:5000"],
            "notify_staff_bell"=>["nullable","boolean"], "notify_staff_sms"=>["nullable","boolean"], "notify_customer_sms"=>["nullable","boolean"],
            "notify_customer_whatsapp"=>["nullable","boolean"], "notify_owner_telegram"=>["nullable","boolean"],
        ]);
        $customer=CustomersInfo::where("customer_unique_id",$data["customer_unique_id"])->firstOrFail();
        $data["ticket_no"]=SupportTicket::generateTicketNo(); $data["ppp_username"]=$customer->pppUser?->username; $data["status"]="new";
        SupportTicket::create($data);
        NotificationLogs::create(["title"=>"Support Ticket Created","message"=>"Ticket #{$data["ticket_no"]} created for {$customer->customer_name}.","status"=>"new","type"=>"Support Ticket"]);
        return redirect()->route("support-center.tickets")->with("support_message","Support ticket {$data["ticket_no"]} created successfully.");
    }

    public function salesQueries(Request $request)
    {
        $this->authorizeSupport();
        $query=SalesQuery::with(["package","assignee"])->latest();
        if($request->filled("status")) $query->where("status",$request->status);
        if($request->filled("lead_source")) $query->where("lead_source",$request->lead_source);
        if($request->filled("q")) $query->where(fn($q)=>$q->where("prospect_name","like","%{$request->q}%")->orWhere("mobile1","like","%{$request->q}%")->orWhere("email","like","%{$request->q}%"));
        $stats=["open"=>SalesQuery::whereIn("status",["new","contacted","follow_up","qualified"])->count(),"new"=>SalesQuery::where("status","new")->count(),"follow_up"=>SalesQuery::where("status","follow_up")->count(),"converted"=>SalesQuery::where("status","converted")->count()];
        return view("xlink.support-center-sales",["queries"=>$query->paginate(25)->withQueryString(),"stats"=>$stats]);
    }

    public function createSalesQuery()
    {
        $this->authorizeSupport();
        return view("xlink.support-center-sales-create", ["packages"=>PackageList::orderBy("package")->get(), "staff"=>$this->staff()]);
    }

    public function storeSalesQuery(Request $request)
    {
        $this->authorizeSupport();
        $data=$request->validate(["prospect_name"=>"required|string|max:160","email"=>"nullable|email|max:190","mobile1"=>"required|string|max:30","mobile2"=>"nullable|string|max:30","nid"=>"nullable|string|max:60","connection_type"=>"required|in:Home (PPPoE),Office (PPPoE),Corporate,Shared / Wi-Fi,Other","package_id"=>"nullable|exists:package_lists,id","expected_date"=>"nullable|date","lead_source"=>"nullable|string|max:80","referred_by"=>"nullable|string|max:160","priority"=>"required|in:low,medium,high,urgent","follow_up_at"=>"nullable|date","floor_flat"=>"nullable|string|max:80","house"=>"nullable|string|max:80","road"=>"nullable|string|max:120","area_id"=>"nullable|string|max:80","area_text"=>"nullable|string|max:160","district"=>"nullable|string|max:100","thana"=>"nullable|string|max:100","assigned_to"=>"nullable|exists:users,id","remarks"=>"nullable|string|max:2000"]);
        $data["status"]="new"; SalesQuery::create($data);
        return redirect()->route("support-center.sales")->with("support_message","Sales query created successfully.");
    }

    public function kyc(Request $request)
    {
        $this->authorizeSupport();
        $query=KycRequest::with("customer")->latest();
        if($request->filled("status")) $query->where("status",$request->status);
        if($request->filled("q")) $query->where(fn($q)=>$q->where("customer_unique_id","like","%{$request->q}%")->orWhere("customer_name","like","%{$request->q}%")->orWhere("phone","like","%{$request->q}%")->orWhere("nid","like","%{$request->q}%"));
        return view("xlink.support-center-kyc",["requests"=>$query->paginate(25)->withQueryString()]);
    }

    public function templates()
    {
        $this->authorizeSupport();
        return view("xlink.support-center-templates", ["templates"=>SupportTicketTemplate::orderBy("sort_order")->get()]);
    }

    public function storeTemplate(Request $request)
    {
        $this->authorizeSupport();
        $data=$request->validate(["type"=>"required|in:complain,task,sales","name"=>"required|string|max:120","sort_order"=>"required|integer|min:0","subject_template"=>"nullable|string|max:190","internal_note_template"=>"nullable|string|max:2000","description_template"=>"nullable|string|max:5000","customer_message"=>"nullable|string|max:3000","staff_message"=>"nullable|string|max:3000"]);
        $data["active"]=(bool)$request->boolean("active"); $data["bell_notification"]=(bool)$request->boolean("bell_notification"); $data["staff_sms"]=(bool)$request->boolean("staff_sms"); $data["customer_sms"]=(bool)$request->boolean("customer_sms"); $data["customer_whatsapp"]=(bool)$request->boolean("customer_whatsapp"); $data["owner_telegram"]=(bool)$request->boolean("owner_telegram"); $data["custom_override"]=(bool)$request->boolean("custom_override");
        SupportTicketTemplate::updateOrCreate(["name"=>$data["name"],"type"=>$data["type"]],$data);
        return back()->with("support_message","Template saved successfully.");
    }
}
