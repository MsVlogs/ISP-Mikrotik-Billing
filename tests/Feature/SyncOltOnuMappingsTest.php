<?php

namespace Tests\Feature;

use App\Models\CustomersInfo;
use App\Models\NetworkInventoryDevice;
use App\Models\OltOnuCustomerMapping;
use App\Models\PPPSecrets;
use App\Services\Olt\OltReadOnlyAdapterManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncOltOnuMappingsTest extends TestCase
{
    use RefreshDatabase;

    private function olt(): NetworkInventoryDevice
    {
        return NetworkInventoryDevice::create([
            'type' => 'olt', 'name' => 'VSOL matcher test', 'vendor' => 'VSOL',
            'model' => 'V1601E04-DP', 'status' => 'online', 'health_status' => 'online',
            'is_active' => true,
        ]);
    }

    private function customer(string $cid, string $username, string $status, string $mac): array
    {
        $ppp = PPPSecrets::create([
            'router_name' => 'MatcherRouter', 'username' => $username, 'password' => 'test-secret',
            'service' => 'pppoe', 'profile' => 'Home', 'status' => $status,
            'caller_id' => '', 'last_caller_id' => $mac,
        ]);
        $customer = CustomersInfo::create([
            'customer_unique_id' => $cid, 'customer_name' => 'Customer '.$cid,
            'status' => 'active', 'ppp_user_id' => $ppp->id,
        ]);

        return [$customer, $ppp];
    }

    private function fakeAdapter(NetworkInventoryDevice $olt, string $mac): void
    {
        $manager = \Mockery::mock(OltReadOnlyAdapterManager::class);
        $manager->shouldReceive('read')->once()->with(\Mockery::on(
            fn ($device) => $device instanceof NetworkInventoryDevice && $device->id === $olt->id
        ))->andReturn([
            'ok' => true, 'status' => 'ok', 'message' => 'test response',
            'onus' => [[
                'onu_id' => '25', 'pon_port' => '1', 'onu_mac' => $mac,
                'status' => 'online', 'snmp_index' => '1.25',
            ]],
        ]);
        $this->app->instance(OltReadOnlyAdapterManager::class, $manager);
    }

    public function test_exact_mac_match_prefers_one_active_ppp_customer_over_removed_legacy_record(): void
    {
        $olt = $this->olt();
        $mac = '00:11:46:95:1c:c5';
        [$removedCustomer, $removedPpp] = $this->customer('BT0027', 'm.shakil', 'removed', $mac);
        [$activeCustomer, $activePpp] = $this->customer('BT0006', 'bt.shakil', 'active', $mac);
        OltOnuCustomerMapping::create([
            'olt_device_id' => $olt->id, 'onu_id' => '25', 'pon_port' => '1',
            'onu_mac' => $mac, 'status' => 'online',
        ]);
        $this->fakeAdapter($olt, $mac);

        $this->artisan('olt:sync-onu-mappings', ['--device' => $olt->id])->assertExitCode(0);

        $mapping = OltOnuCustomerMapping::where('olt_device_id', $olt->id)->where('onu_id', '25')->firstOrFail();
        $this->assertSame($activeCustomer->id, $mapping->customer_id);
        $this->assertSame($activePpp->id, $mapping->ppp_user_id);
        $this->assertNotSame($removedCustomer->id, $mapping->customer_id);
        $this->assertNotSame($removedPpp->id, $mapping->ppp_user_id);
    }

    public function test_multiple_active_ppp_accounts_with_same_onu_mac_remain_unmapped(): void
    {
        $olt = $this->olt();
        $mac = '00:11:46:95:1c:c5';
        $this->customer('BT0006', 'bt.shakil', 'active', $mac);
        $this->customer('BT0044', 'other.active.shakil', 'active', $mac);
        OltOnuCustomerMapping::create([
            'olt_device_id' => $olt->id, 'onu_id' => '25', 'pon_port' => '1',
            'onu_mac' => $mac, 'status' => 'online',
        ]);
        $this->fakeAdapter($olt, $mac);

        $this->artisan('olt:sync-onu-mappings', ['--device' => $olt->id])->assertExitCode(0);

        $mapping = OltOnuCustomerMapping::where('olt_device_id', $olt->id)->where('onu_id', '25')->firstOrFail();
        $this->assertNull($mapping->customer_id);
        $this->assertNull($mapping->ppp_user_id);
    }
}
