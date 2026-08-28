<?php

namespace Tests\Feature;

use App\Livewire\MikrotikClients;
use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MikrotikClientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::findOrCreate('Super Admin');
        $user = User::create(['name' => 'Mikrotik Test', 'email' => uniqid('mikrotik_', true).'@test.local', 'mobile' => uniqid('01'), 'password' => bcrypt('password')]);
        $user->assignRole($role);
        $this->actingAs($user);
    }

    private function router(string $name): RouterList
    {
        return RouterList::create([
            'router_name' => $name, 'ip_address' => '127.0.0.1',
            'username' => 'test', 'password' => 'test', 'action' => 'connected',
        ]);
    }

    public function test_unlinked_ppp_secret_is_in_pending_list(): void
    {
        $router = $this->router('test-router');
        PPPSecrets::create(['router_name' => $router->router_name, 'username' => 'pending-user', 'service' => 'pppoe']);

        Livewire::test(MikrotikClients::class)
            ->set('router', $router->router_name)
            ->assertSee('pending-user');
    }

    public function test_linked_ppp_secret_is_not_in_pending_list(): void
    {
        $router = $this->router('linked-router');
        $secret = PPPSecrets::create(['router_name' => $router->router_name, 'username' => 'linked-user', 'service' => 'pppoe']);
        CustomersInfo::create(['customer_unique_id' => 'TEST-LINKED', 'ppp_user_id' => $secret->id]);

        Livewire::test(MikrotikClients::class)
            ->set('router', $router->router_name)
            ->assertDontSee('linked-user');
    }
}
