<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunicationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);
        return $user;
    }

    public function test_communication_center_dashboard_renders_for_authenticated_admin(): void
    {
        $response = $this->actingAs($this->admin())->get('/communication-center');
        $response->assertOk();
        $response->assertViewIs('xlink.communication-center');
    }

    public function test_communication_center_tabs_render_for_authenticated_admin(): void
    {
        foreach (['chat', 'settings', 'whatsapp'] as $tab) {
            $response = $this->actingAs($this->admin())->get('/communication-center/'.$tab);
            $response->assertOk();
            $response->assertViewIs('xlink.communication-center');
        }
    }

    public function test_communication_center_requires_authentication(): void
    {
        $this->get('/communication-center')->assertRedirect();
    }
}
