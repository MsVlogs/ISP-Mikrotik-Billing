<?php

namespace Tests\Feature;

use App\Models\CustomersInfo;
use App\Models\KycRequest;
use App\Models\MainSiteData;
use App\Models\SupportTicket;
use App\Models\SupportTicketTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SupportCenterParityTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    #[Test]
    public function support_center_routes_require_authentication(): void
    {
        foreach (['/support-center','/support-center/tickets','/support-center/settings','/support-center/templates','/support-center/kyc'] as $route) {
            $response = $this->get($route);
            $response->assertStatus(302);
            $this->assertStringContainsString('/login', $response->headers->get('Location'));
        }
    }

    #[Test]
    public function support_settings_can_be_saved_by_super_admin(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post('/support-center/settings', [
            'default_priority' => 'high',
            'auto_close_days' => 14,
            'notify_sms' => '1',
        ])->assertSessionHas('support_message');

        $this->assertSame('high', MainSiteData::getValue('support_default_priority'));
        $this->assertSame('14', (string) MainSiteData::getValue('support_auto_close_days'));
        $this->assertSame('1', (string) MainSiteData::getValue('support_notify_sms'));
    }

    #[Test]
    public function super_admin_can_export_tickets(): void
    {
        $user = $this->superAdmin();
        SupportTicket::create([
            'ticket_no'=>'TKT-EXPORT01','customer_unique_id'=>'CUS-EXPORT01','subject'=>'Export me','description'=>'Export test','priority'=>'low','status'=>'new',
        ]);

        $response = $this->actingAs($user)->get('/support-center/tickets/export');
        $response->assertOk();
        $this->assertStringContainsString('TKT-EXPORT01', $response->streamedContent());
    }

    #[Test]
    public function selected_tickets_can_be_bulk_updated(): void
    {
        $user = $this->superAdmin();
        $a = SupportTicket::create(['ticket_no'=>'TKT-PAR001','customer_unique_id'=>'CUS-PAR001','subject'=>'A','description'=>'A','priority'=>'low','status'=>'new']);
        $b = SupportTicket::create(['ticket_no'=>'TKT-PAR002','customer_unique_id'=>'CUS-PAR002','subject'=>'B','description'=>'B','priority'=>'medium','status'=>'open']);

        $this->actingAs($user)->post('/support-center/tickets/bulk', [
            'ticket_ids' => [$a->id, $b->id],
            'status' => 'resolved',
        ])->assertSessionHas('support_message');

        $this->assertDatabaseHas('support_tickets', ['id'=>$a->id,'status'=>'resolved']);
        $this->assertDatabaseHas('support_tickets', ['id'=>$b->id,'status'=>'resolved']);
    }

    #[Test]
    public function selected_kyc_requests_can_be_bulk_updated(): void
    {
        $user = $this->superAdmin();
        $a = KycRequest::create(['customer_unique_id'=>'CUS-KYC01','customer_name'=>'A','status'=>'pending']);
        $b = KycRequest::create(['customer_unique_id'=>'CUS-KYC02','customer_name'=>'B','status'=>'pending']);

        $this->actingAs($user)->post('/support-center/kyc/bulk', [
            'kyc_ids' => [$a->id, $b->id],
            'status' => 'reviewed',
        ])->assertSessionHas('support_message');

        $this->assertDatabaseHas('kyc_requests', ['id'=>$a->id,'status'=>'reviewed']);
        $this->assertDatabaseHas('kyc_requests', ['id'=>$b->id,'status'=>'reviewed']);
    }

    #[Test]
    public function sales_query_source_alias_is_supported(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->get('/support-center/sales-queries?source=Facebook')->assertOk();
    }

    #[Test]
    public function kyc_date_filters_are_supported(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->get('/support-center/kyc?date_from=2026-01-01&date_to=2026-12-31')->assertOk();
    }

    #[Test]
    public function templates_page_renders_for_super_admin(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get('/support-center/templates')
            ->assertOk()
            ->assertSee('Ticket Templates & Rules', false)
            ->assertSee('General Support Settings');
    }

    #[Test]
    public function template_parity_fields_are_persisted(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post('/support-center/templates', [
            'type'=>'complain','name'=>'Connection Problem','sort_order'=>1,
            'subject_template'=>'#TOPIC# - #CID#','description_template'=>'desc',
            'body_template'=>'body','customer_message'=>'customer','customer_message_template'=>'customer2',
            'staff_message'=>'staff','staff_message_template'=>'staff2',
            'allow_custom_channels'=>'1','active'=>'1','bell_notification'=>'1','staff_sms'=>'1','customer_sms'=>'1',
        ])->assertSessionHas('support_message');

        $template = SupportTicketTemplate::where('type','complain')->where('name','Connection Problem')->firstOrFail();
        $this->assertSame('body', $template->body_template);
        $this->assertSame('customer2', $template->customer_message_template);
        $this->assertTrue($template->allow_custom_channels);
    }
}
