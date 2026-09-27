<?php

namespace App\Http\Controllers;

use App\Models\CustomersInfo;
use App\Models\OltOnuCustomerMapping;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

class CustomerDetailsController extends Controller
{
    public function show(string $id)
    {
        if (! hasAccess(['Super Admin'], ['view-customer', 'all-customer'])) {
            abort(403, 'Unauthorized action.');
        }

        $uniqueId = $this->resolveCustomerId($id);
        $customer = CustomersInfo::withTrashed()
            ->with(['pppUser', 'package', 'billing', 'official', 'customerAddress'])
            ->where('customer_unique_id', $uniqueId)
            ->firstOrFail();

        $mapping = Schema::hasTable('olt_onu_customer_mappings')
            ? OltOnuCustomerMapping::with('olt')
                ->where('customer_id', $customer->id)
                ->latest('id')->first()
            : null;

        $activities = class_exists(Activity::class)
            ? Activity::with('causer')->where(function ($q) use ($uniqueId, $customer) {
                $q->where('properties->customer_unique_id', $uniqueId)
                  ->orWhere('properties->customer_id', $customer->id);
            })->latest()->limit(8)->get()
            : collect();

        return view('customers.show', compact('customer', 'mapping', 'activities'));
    }

    private function resolveCustomerId(string $id): string
    {
        try {
            $decrypted = decrypt($id);
            if (is_string($decrypted) && $decrypted !== '') {
                return $decrypted;
            }
        } catch (\Throwable) {
            // The public customer URL may use the manual Customer Unique ID.
        }

        return $id;
    }
}
