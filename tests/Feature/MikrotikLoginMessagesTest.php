<?php

namespace Tests\Feature;

use App\Livewire\MikrotikLoginMessages;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MikrotikLoginMessagesTest extends TestCase
{
    #[Test]
    public function it_classifies_authentication_failures(): void
    {
        $this->assertSame('auth_failed', MikrotikLoginMessages::classifyEvent('authentication failed for admin'));
        $this->assertSame('auth_failed', MikrotikLoginMessages::classifyEvent('invalid credentials', 'system,error'));
    }

    #[Test]
    public function it_classifies_login_and_logout_events(): void
    {
        $this->assertSame('login', MikrotikLoginMessages::classifyEvent('user admin logged in'));
        $this->assertSame('login', MikrotikLoginMessages::classifyEvent('client authenticated'));
        $this->assertSame('logout', MikrotikLoginMessages::classifyEvent('user admin logged out'));
        $this->assertSame('logout', MikrotikLoginMessages::classifyEvent('client disconnected'));
    }

    #[Test]
    public function it_returns_other_for_unrelated_messages(): void
    {
        $this->assertSame('other', MikrotikLoginMessages::classifyEvent('interface ether1 link up'));
    }
}
