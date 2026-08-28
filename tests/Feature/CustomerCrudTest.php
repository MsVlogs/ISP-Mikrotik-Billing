<?php

namespace Tests\Feature;

use App\Models\CustomersInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_be_created_with_required_fields(): void
    {
        $customer = CustomersInfo::create([
            'customer_unique_id' => 'CUST-TEST-001',
            'customer_name' => 'Test Customer',
            'mobile' => '8801700000000',
            'status' => 'active',
            'disable_count' => 0,
        ]);

        $this->assertDatabaseHas('customers_infos', [
            'id' => $customer->id,
            'customer_unique_id' => 'CUST-TEST-001',
            'customer_name' => 'Test Customer',
        ]);
    }

    public function test_active_scope_returns_only_active_customers(): void
    {
        CustomersInfo::create(['customer_unique_id' => 'CUST-A', 'customer_name' => 'Active', 'mobile' => '8801700000001', 'status' => 'active', 'disable_count' => 0]);
        CustomersInfo::create(['customer_unique_id' => 'CUST-I', 'customer_name' => 'Inactive', 'mobile' => '8801700000002', 'status' => 'inactive', 'disable_count' => 0]);

        $this->assertSame(1, CustomersInfo::active()->count());
        $this->assertSame('Active', CustomersInfo::active()->first()->customer_name);
    }

    public function test_customer_soft_delete_is_persisted(): void
    {
        $customer = CustomersInfo::create(['customer_unique_id' => 'CUST-D', 'customer_name' => 'Delete Test', 'mobile' => '8801700000003', 'status' => 'active', 'disable_count' => 0]);
        $customer->delete();

        $this->assertSoftDeleted('customers_infos', ['id' => $customer->id]);
        $this->assertNull(CustomersInfo::find($customer->id));
    }
}
