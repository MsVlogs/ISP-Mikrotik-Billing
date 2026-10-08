<?php

namespace Tests\Feature;

use App\Models\CustomersInfo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCidReuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_cid_can_be_reused_after_customer_is_soft_deleted(): void
    {
        $oldCustomer = CustomersInfo::create([
            'customer_unique_id' => 'BT0029',
            'customer_name' => 'Old Customer',
            'status' => 'active',
        ]);

        $oldCustomer->delete();

        $newCustomer = CustomersInfo::create([
            'customer_unique_id' => 'BT0029',
            'customer_name' => 'Replacement Customer',
            'status' => 'active',
        ]);

        $this->assertSame('BT0029', $newCustomer->customer_unique_id);
        $this->assertSoftDeleted('customers_infos', ['id' => $oldCustomer->id]);
        $this->assertDatabaseHas('customers_infos', [
            'id' => $newCustomer->id,
            'customer_unique_id' => 'BT0029',
            'deleted_at' => null,
        ]);
    }

    public function test_cid_still_cannot_be_used_by_two_live_customers(): void
    {
        CustomersInfo::create([
            'customer_unique_id' => 'BT0029',
            'customer_name' => 'First Customer',
            'status' => 'active',
        ]);

        $this->expectException(QueryException::class);

        CustomersInfo::create([
            'customer_unique_id' => 'BT0029',
            'customer_name' => 'Second Customer',
            'status' => 'active',
        ]);
    }
}
