<?php

namespace App\Console\Commands;

use App\Models\CustomersInfo;
use App\Models\NetworkInventoryDevice;
use App\Models\OltOnuCustomerMapping;
use App\Models\PPPSecrets;
use App\Services\Olt\OltReadOnlyAdapterManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncOltOnuMappings extends Command
{
    protected $signature = 'olt:sync-onu-mappings {--device= : OLT device ID} {--dry-run : Read and resolve mappings without writing the local database}';
    protected $description = 'Read-only sync of ONU/customer mappings from OLT read-only adapters or configured telemetry';

    public function handle(OltReadOnlyAdapterManager $manager): int
    {
        $query = NetworkInventoryDevice::where('type', 'olt');
        if ($this->option('device')) $query->whereKey((int) $this->option('device'));

        foreach ($query->get() as $olt) {
            $result = $this->readSource($olt, $manager);
            if (!$result['ok']) { $this->warn("OLT {$olt->id}: {$result['message']}"); continue; }

            $rows = is_array($result['onus'] ?? null) ? $result['onus'] : [];
            if (!$rows) { $this->info("OLT {$olt->id}: no normalized ONU rows returned; no mapping writes performed."); continue; }

            $count = 0; $skipped = 0; $issues = [];
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $onuId = $row['onu_id'] ?? $row['onuId'] ?? $row['id'] ?? $row['snmp_index'] ?? null;
                if ($onuId === null || trim((string) $onuId) === '') { $skipped++; $issues[] = 'missing ONU id'; continue; }
                $onuId = trim((string) $onuId);

                $username = $row['pppoe_username'] ?? $row['pppoeUsername'] ?? $row['username'] ?? null;
                $onuMac = $row['onu_mac'] ?? $row['mac'] ?? $row['onuMac'] ?? null;
                $pppId = null; $customerId = null; $autoMatch = null;
                $existingMapping = OltOnuCustomerMapping::where('olt_device_id', $olt->id)
                    ->where('onu_id', $onuId)->first();
                if ($username !== null && $username !== '') {
                    $ppps = PPPSecrets::where('username', (string) $username)->limit(2)->get();
                    if ($ppps->count() === 1) {
                        $ppp = $ppps->first();
                        $pppId = $ppp->id; $customerId = CustomersInfo::where('ppp_user_id', $ppp->id)->value('id');
                        $autoMatch = 'username';
                    }
                }
                if ($pppId === null && $onuMac !== null && trim((string) $onuMac) !== '') {
                    $normalizedMac = $this->normalizeMac($onuMac);
                    if ($normalizedMac !== '') {
                        $ppps = PPPSecrets::query()
                            ->whereNotNull('caller_id')
                            ->orWhereNotNull('last_caller_id')
                            ->get(['id', 'caller_id', 'last_caller_id']);
                        $matches = $ppps->filter(function ($ppp) use ($normalizedMac) {
                            return $this->normalizeMac($ppp->caller_id) === $normalizedMac
                                || $this->normalizeMac($ppp->last_caller_id) === $normalizedMac;
                        })->values();
                        if ($matches->count() === 1) {
                            $ppp = $matches->first();
                            $pppId = $ppp->id;
                            $customerId = CustomersInfo::where('ppp_user_id', $ppp->id)->value('id');
                            $autoMatch = 'mac';
                        }
                    }
                }
                if ($pppId === null && $existingMapping) {
                    // OLT telemetry often has no PPPoE username. Never erase an operator's
                    // existing customer/PPPoE mapping just because telemetry omitted it.
                    $pppId = $existingMapping->ppp_user_id;
                    $customerId = $existingMapping->customer_id;
                }

                $status = $this->normalizeStatus($row);
                if (!in_array($status, ['online', 'offline', 'unknown'], true)) {
                    $issues[] = "ONU {$onuId}: invalid status normalized to unknown";
                    $status = 'unknown';
                }
                $lastSeen = $row['last_seen'] ?? $row['lastSeen'] ?? null;
                if ($lastSeen !== null && $lastSeen !== '' && !strtotime((string) $lastSeen)) {
                    $issues[] = "ONU {$onuId}: invalid last_seen ignored";
                    $lastSeen = null;
                }
                // A successful live poll is itself evidence that an online ONU was seen now.
                // Preserve the previous timestamp when telemetry omits last_seen for an offline/unknown ONU.
                if (($lastSeen === null || $lastSeen === '') && $status === 'online') {
                    $lastSeen = now();
                } elseif (($lastSeen === null || $lastSeen === '') && $existingMapping?->last_seen_at) {
                    $lastSeen = $existingMapping->last_seen_at;
                }

                if ($this->option('dry-run')) {
                    $this->line("OLT {$olt->id}: ONU {$onuId} → customer=".($customerId ?? 'unmapped').", ppp=".($pppId ?? 'unmapped').", status={$status}");
                    $count++;
                    continue;
                }

                OltOnuCustomerMapping::updateOrCreate(
                    ['olt_device_id' => $olt->id, 'onu_id' => (string) $onuId],
                    [
                        'customer_id' => $customerId ?? $existingMapping?->customer_id,
                        'ppp_user_id' => $pppId ?? $existingMapping?->ppp_user_id,
                        'onu_serial' => $row['onu_serial'] ?? $row['serial'] ?? $row['onuSerial'] ?? null,
                        'onu_mac' => $onuMac,
                        'pon_port' => $row['pon_port'] ?? $row['ponPort'] ?? $row['pon'] ?? null,
                        'onu_type' => $row['onu_type'] ?? $row['type'] ?? null,
                        'status' => $status,
                        'rx_power' => $row['rx_power'] ?? $row['opticalRx'] ?? null,
                        'tx_power' => $row['tx_power'] ?? $row['opticalTx'] ?? null,
                        'onu_ip' => $row['onu_ip'] ?? $row['ip'] ?? $row['onuIp'] ?? null,
                        'last_seen_at' => $lastSeen,
                        'notes' => $existingMapping ? $existingMapping->notes : ($autoMatch ? 'Auto-mapped by '.$autoMatch : ($row['detail'] ?? null)),
                    ]
                );
                $count++;
            }
            $this->info("OLT {$olt->id}: normalized {$count} ONU rows synced; skipped {$skipped} invalid rows.");
            foreach (array_slice($issues, 0, 10) as $issue) $this->warn($issue);
            if (count($issues) > 10) $this->warn('Additional mapping validation issues: '.(count($issues)-10));
        }
        return self::SUCCESS;
    }

    private function readSource(NetworkInventoryDevice $olt, OltReadOnlyAdapterManager $manager): array
    {
        try {
            return $manager->read($olt);
        } catch (\Throwable) {
            return $this->readLegacyTelemetry($olt);
        }
    }

    private function readLegacyTelemetry(NetworkInventoryDevice $olt): array
    {
        $url = trim((string) env('OLT_TELEMETRY_URL', ''));
        if ($url === '') return ['ok' => false, 'status' => 'not_configured', 'message' => 'No read-only OLT adapter or OLT_TELEMETRY_URL is configured.', 'onus' => []];

        $endpoint = rtrim($url, '/') . '/onu/status?oltId=' . urlencode((string) $olt->id);
        try {
            $request = Http::timeout(5)->acceptJson();
            $token = trim((string) env('OLT_TELEMETRY_TOKEN', ''));
            if ($token !== '') $request = $request->withToken($token);
            $response = $request->get($endpoint);
            if (!$response->successful()) return ['ok' => false, 'status' => 'unreachable', 'message' => "Telemetry HTTP {$response->status()}.", 'onus' => []];

            $rows = $response->json();
            if (isset($rows['onus'])) $rows = $rows['onus'];
            return ['ok' => is_array($rows), 'status' => is_array($rows) ? 'ok' : 'invalid_response', 'message' => is_array($rows) ? 'Legacy telemetry read completed.' : 'Telemetry response was not an ONU array.', 'onus' => is_array($rows) ? $rows : []];
        } catch (\Throwable) {
            return ['ok' => false, 'status' => 'unreachable', 'message' => 'Legacy OLT telemetry is unavailable; no write performed.', 'onus' => []];
        }
    }

    private function normalizeMac($mac): string
    {
        return strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $mac) ?? '');
    }

    private function normalizeStatus(array $row): string
    {
        if (array_key_exists('online', $row)) return ($row['online'] === true || $row['online'] === 1 || $row['online'] === '1') ? 'online' : 'offline';
        $status = strtolower(trim((string) ($row['status'] ?? 'unknown')));
        if (in_array($status, ['1', 'online', 'up', 'active', 'registered'], true)) return 'online';
        if (in_array($status, ['2', '4', 'offline', 'down', 'inactive', 'unregistered', 'deregistered', 'lost'], true)) return 'offline';
        if (in_array($status, ['0', '3', '5', 'authenticated', 'auto_config', 'standby', 'unknown'], true)) return 'unknown';
        return $status !== '' ? $status : 'unknown';
    }
}
