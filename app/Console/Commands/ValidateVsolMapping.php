<?php

namespace App\Console\Commands;

use App\Models\CustomersInfo;
use App\Models\NetworkInventoryDevice;
use App\Models\PPPSecrets;
use Illuminate\Console\Command;

class ValidateVsolMapping extends Command
{
    protected $signature = 'olt:validate-onu-mapping {--device=3}';

    protected $description = 'Read-only validation of live ONU MAC matches against PPP caller IDs';

    public function handle(): int
    {
        $device = NetworkInventoryDevice::findOrFail((int) $this->option('device'));
        $result = app(\App\Services\Olt\OltReadOnlyAdapterManager::class)->read($device);
        $macs = collect($result['onus'] ?? [])
            ->pluck('onu_mac')->filter()
            ->map(fn ($m) => strtolower(str_replace('-', ':', trim($m))));

        $rows = PPPSecrets::query()->whereNotNull('last_caller_id')
            ->get(['id', 'last_caller_id'])
            ->filter(fn ($row) => $macs->contains(strtolower(str_replace('-', ':', trim($row->last_caller_id)))));

        $groups = $rows->groupBy(fn ($row) => strtolower(str_replace('-', ':', trim($row->last_caller_id))));
        $duplicateGroups = $groups->filter(fn ($group) => $group->count() > 1)->count();
        $uniqueRows = $groups->filter(fn ($group) => $group->count() === 1)->flatten(1);
        $uniqueWithOneCustomer = $uniqueRows->filter(fn ($row) => CustomersInfo::where('ppp_user_id', $row->id)->count() === 1)->count();
        $uniqueWithoutCustomer = $uniqueRows->filter(fn ($row) => CustomersInfo::where('ppp_user_id', $row->id)->count() === 0)->count();

        $this->table(['Metric', 'Value'], [
            ['Live ONU count', $macs->count()],
            ['Exact last_caller_id MAC matches', $rows->count()],
            ['Duplicate MAC groups', $duplicateGroups],
            ['Unique MAC match rows', $uniqueRows->count()],
            ['Unique rows with exactly one customer', $uniqueWithOneCustomer],
            ['Unique rows without a customer', $uniqueWithoutCustomer],
            ['Saved mappings currently', \App\Models\OltOnuCustomerMapping::where('olt_device_id', $device->id)->count()],
        ]);

        $this->warn('Read-only check only: a MAC match does not by itself prove the ONU-to-customer relationship. No database writes were performed.');

        return self::SUCCESS;
    }
}
