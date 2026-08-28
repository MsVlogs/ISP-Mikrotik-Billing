<?php

namespace Tests\Feature;

use Tests\TestCase;

class SweetBillingParityTest extends TestCase
{
    /** @test */
    public function sweet_billing_control_center_requires_authentication(): void
    {
        $response = $this->get('/sweet-billing');
        $response->assertStatus(302);
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    /** @test */
    public function sweet_billing_module_routes_require_authentication(): void
    {
        $routes = [
            '/mobile-banking', '/partner-network', '/bandwidth-reseller',
            '/devices-inventory', '/stock-inventory', '/communication-center',
            '/support-center', '/team-access', '/system-settings',
            '/billing-helpline', '/profile-security',
        ];
        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertStatus(302);
            $this->assertStringContainsString('/login', $response->headers->get('Location'));
        }
    }

    /** @test */
    public function network_monitoring_routes_remain_available(): void
    {
        foreach ([
            '/network-map', '/traffic-monitor', '/high-usage-monitor',
            '/device-watcher', '/mikrotik-login-messages',
        ] as $route) {
            $response = $this->get($route);
            $response->assertStatus(302);
            $this->assertStringContainsString('/login', $response->headers->get('Location'));
        }
    }
}
