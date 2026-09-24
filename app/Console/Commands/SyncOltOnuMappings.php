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

            $count = 0;
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $onuId = $row['onu_id'] ?? $row['onuId'] ?? $row['id'] ?? $row['snmp_index'] ?? null;
                if ($onuId === null || $onuId === '') continue;

                $username = $row['pppoe_username'] ?? $row['pppoeUsername'] ?? $row['username'] ?? null;
                $pppId = null; $customerId = null;
                if ($username !== null && $username !== '') {
                    $ppp = PPPSecrets::where('username', (string) $username)->first();
                    if ($ppp) { $pppId = $ppp->id; $customerId = CustomersInfo::where('ppp_user_id', $ppp->id)->value('id'); }
                }

                if ($this->option('dry-run')) {
                    $this->line("OLT {$olt->id}: ONU {$onuId} → customer=".($customerId ?? 'unmapped').", ppp=".($pppId ?? 'unmapped'));
                    $count++;
                    continue;
                }

                OltOnuCustomerMapping::updateOrCreate(
                    ['olt_device_id' => $olt->id, 'onu_id' => (string) $onuId],
                    [
                        'customer_id' => $customerId, 'ppp_user_id' => $pppId,
                        'onu_serial' => $row['onu_serial'] ?? $row['serial'] ?? $row['onuSerial'] ?? null,
                        'onu_mac' => $row['onu_mac'] ?? $row['mac'] ?? $row['onuMac'] ?? null,
                        'pon_port' => $row['pon_port'] ?? $row['ponPort'] ?? $row['pon'] ?? null,
                        'onu_type' => $row['onu_type'] ?? $row['type'] ?? null,
                        'status' => $this->normalizeStatus($row),
                        'rx_power' => $row['rx_power'] ?? $row['opticalRx'] ?? null,
                        'tx_power' => $row['tx_power'] ?? $row['opticalTx'] ?? null,
                        'onu_ip' => $row['onu_ip'] ?? $row['ip'] ?? $row['onuIp'] ?? null,
                        'last_seen_at' => $row['last_seen'] ?? $row['lastSeen'] ?? null,
                        'notes' => $row['detail'] ?? null,
                    ]
                );
                $count++;
            }
            $this->info("OLT {$olt->id}: normalized {$count} ONU rows synced.");
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

    private function normalizeStatus(array $row): string
    {
        if (array_key_exists('online', $row)) return ($row['online'] === true || $row['online'] === 1 || $row['online'] === '1') ? 'online' : 'offline';
        $status = strtolower(trim((string) ($row['status'] ?? 'unknown')));
        if (in_array($status, ['1', 'online', 'up', 'active', 'registered'], true)) return 'online';
        if (in_array($status, ['0', 'offline', 'down', 'inactive', 'unregistered'], true)) return 'offline';
        return $status !== '' ? $status : 'unknown';
    }
}
