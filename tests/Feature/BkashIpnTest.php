<?php

namespace Tests\Feature;

use App\Http\Controllers\Payment\WebhookPaymentController;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BkashIpnTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_invalid_amount(): void
    {
        $response = $this->postJson('/api/payment/bkash/ipn', [
            'trxID' => 'TRX-INVALID-AMOUNT',
            'amount' => 0,
            'payerReference' => 'CUS-001',
        ]);

        $response->assertStatus(422)
            ->assertJson(['status' => 'error', 'message' => 'Invalid amount']);
    }

    public function test_it_returns_customer_not_found_for_unknown_reference(): void
    {
        $response = $this->postJson('/api/payment/bkash/ipn', [
            'trxID' => 'TRX-UNKNOWN-CUSTOMER',
            'amount' => 100,
            'payerReference' => 'CUS-404',
            'transactionStatus' => 'Completed',
        ]);

        $response->assertStatus(404)
            ->assertJson(['status' => 'error', 'message' => 'Customer not found']);
    }

    public function test_it_returns_duplicate_when_payment_service_reports_already_processed(): void
    {
        $customer = CustomersInfo::create([
            'customer_unique_id' => 'CUS-DUP-001',
            'customer_name' => 'Duplicate Test',
            'status' => 'active',
        ]);

        $service = $this->mock(PaymentService::class);
        $service->shouldReceive('processSuccessPayment')
            ->once()
            ->withArgs(function ($receivedCustomer, $amount, $gateway, $trxId) use ($customer) {
                return $receivedCustomer->is($customer)
                    && $amount === 100.0
                    && $gateway === 'bkash'
                    && $trxId === 'TRX-DUP-001';
            })
            ->andReturnFalse();

        $response = $this->postJson('/api/payment/bkash/ipn', [
            'trxID' => 'TRX-DUP-001',
            'amount' => '100',
            'payerReference' => 'CUS-DUP-001',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'duplicate',
                'message' => 'Payment already processed',
                'transaction_id' => 'TRX-DUP-001',
            ]);
    }

    public function test_payment_service_ignores_a_paid_transaction_id(): void
    {
        $customer = CustomersInfo::create([
            'customer_unique_id' => 'CUS-SERVICE-DUP',
            'customer_name' => 'Service Duplicate Test',
            'status' => 'active',
        ]);

        CollectionSummary::create([
            'customer_collection_unique_id' => $customer->customer_unique_id,
            'collection_date' => now(),
            'collection_amount' => 100,
            'collected_by' => 'Online Payment (BKASH)',
            'payment_type' => 'online',
            'payment_method' => 'bkash',
            'transaction_id' => 'TRX-SERVICE-DUP',
            'payment_status' => 'paid',
            'invoice_no' => 999991,
            'bill_month' => now()->format('F Y'),
        ]);

        $this->assertFalse(
            app(PaymentService::class)->processSuccessPayment($customer, 100.0, 'bkash', 'TRX-SERVICE-DUP')
        );
    }

    public function test_collection_transaction_id_is_unique(): void
    {
        $this->assertTrue(Schema::hasColumn('collection_summaries', 'transaction_id'));

        CollectionSummary::create([
            'customer_collection_unique_id' => 'CUS-UNIQUE-1',
            'collection_date' => now(),
            'collection_amount' => 50,
            'payment_method' => 'bkash',
            'transaction_id' => 'TRX-UNIQUE-001',
            'payment_status' => 'paid',
            'invoice_no' => 999992,
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        CollectionSummary::create([
            'customer_collection_unique_id' => 'CUS-UNIQUE-2',
            'collection_date' => now(),
            'collection_amount' => 75,
            'payment_method' => 'bkash',
            'transaction_id' => 'TRX-UNIQUE-001',
            'payment_status' => 'paid',
            'invoice_no' => 999993,
        ]);
    }
}
