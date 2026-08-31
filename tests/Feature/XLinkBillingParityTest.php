<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class XLinkBillingParityTest extends TestCase
{
    #[Test]
    public function xlink_billing_control_center_requires_authentication(): void
    {
        $response = $this->get('/xlink-billing');
        $response->assertStatus(302);
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    #[Test]
    public function xlink_billing_module_routes_require_authentication(): void
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

    #[Test]
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
