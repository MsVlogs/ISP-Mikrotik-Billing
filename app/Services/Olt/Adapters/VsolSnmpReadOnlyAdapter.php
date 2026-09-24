<?php
namespace App\Services\Olt\Adapters;

use App\Contracts\OltReadOnlyAdapter;
use App\Models\NetworkInventoryDevice;
use Illuminate\Support\Facades\Crypt;

class VsolSnmpReadOnlyAdapter implements OltReadOnlyAdapter
{
    public function key(): string { return 'vsol_snmp_readonly'; }

    public function supports(NetworkInventoryDevice $device): bool
    {
        return $device->type === 'olt'
            && strcasecmp((string) $device->vendor, 'VSOL') === 0
            && data_get($this->config($device), 'transport', 'snmp') === 'snmp';
    }

    public function read(NetworkInventoryDevice $device): array
    {
        if (!function_exists('snmp2_get')) return $this->fail($device, 'PHP SNMP extension is not installed.');

        $config = $this->config($device);
        $host = (string) ($device->ip_address ?: $device->host);
        $community = (string) ($config['community'] ?? $this->storedCommunity($device));

        if ($host === '') return $this->fail($device, 'OLT management host/IP is not configured.');
        if ($community === '') return $this->fail($device, 'SNMP community is not configured for the adapter.');

        $timeout = max(100000, (int) ($config['timeout_us'] ?? 1000000));
        $retries = max(0, (int) ($config['retries'] ?? 1));

        // Standard SNMP system OIDs provide a safe connectivity/identity probe.
        // Vendor-specific ONU OIDs remain configuration-driven and are never guessed.
        $oids = (array) ($config['oids'] ?? []);
        if (!$oids) {
            $oids = [
                'sysDescr' => '1.3.6.1.2.1.1.1.0',
                'sysUpTime' => '1.3.6.1.2.1.1.3.0',
            ];
        }

        $result = [
            'ok' => false,
            'status' => 'reachable',
            'message' => 'Read-only VSOL SNMP probe completed.',
            'device' => [
                'id' => $device->id,
                'host' => $host,
                'vendor' => $device->vendor,
                'model' => $device->model,
                'transport' => 'snmp',
                'version' => strtoupper((string) ($config['version'] ?? $device->snmp_version ?? '2C')),
            ],
            'onus' => [],
            'meta' => [
                'read_only' => true,
                'writes_performed' => false,
                'oid_count' => 0,
                'onu_count' => 0,
                'onu_discovery_configured' => !empty($config['onu_oids']),
            ],
        ];

        foreach ($oids as $name => $oid) {
            if (!is_string($name) || !is_string($oid) || trim($oid) === '' || !preg_match('/^(?:\\.?(?:\\d+\\.)*\\d+)$/', trim($oid))) continue;
            $value = @snmp2_get($host, $community, trim($oid), $timeout, $retries);
            $result['meta']['oid_count']++;
            if ($value === false) { $result['meta']['failed_oids'][] = $name; continue; }
            $result['device'][$name] = trim(preg_replace('/^[A-Z0-9-]+:\\s*/i', '', $value) ?? $value);
        }

        $onuConfig = (array) ($config['onu_oids'] ?? []);
        if ($onuConfig) {
            $result['onus'] = $this->discoverOnus($host, $community, $onuConfig, $timeout, $retries);
            $result['meta']['onu_count'] = count($result['onus']);
            $result['meta']['onu_discovery_configured'] = true;
        }

        if ($result['meta']['oid_count'] === 0 && !$result['meta']['onu_discovery_configured']) {
            $result['status'] = 'not_configured';
            $result['message'] = 'VSOL adapter is ready, but no valid OID mappings are configured.';
            return $result;
        }

        $hasDeviceData = $result['meta']['oid_count'] > 0 && empty($result['meta']['failed_oids']);
        $hasOnuData = $result['meta']['onu_count'] > 0;
        $hasFailures = !empty($result['meta']['failed_oids']);
        $result['ok'] = $hasDeviceData || $hasOnuData;
        $result['status'] = $result['ok'] ? ($hasFailures ? 'partial' : 'ok') : 'unreachable';
        $result['message'] = $result['status'] === 'ok'
            ? 'Configured VSOL SNMP OIDs and ONU discovery were read successfully.'
            : ($result['status'] === 'partial'
                ? 'VSOL SNMP returned usable read-only data, but one or more configured probes failed.'
                : 'VSOL SNMP read failed. No OLT changes were made.');

        return $result;
    }

    private function discoverOnus(string $host, string $community, array $onuConfig, int $timeout, int $retries): array
    {
        $tables = [];
        foreach ($onuConfig as $field => $baseOid) {
            if (!is_string($field) || !is_string($baseOid) || !preg_match('/^(?:\\.?(?:\\d+\\.)*\\d+)$/', trim($baseOid))) continue;
            $walk = @snmp2_real_walk($host, $community, trim($baseOid), $timeout, $retries);
            if (!is_array($walk)) continue;
            foreach ($walk as $returnedOid => $value) {
                $index = $this->oidIndex((string) $returnedOid, trim($baseOid));
                if ($index === null) continue;
                $tables[$index][$field] = $this->cleanSnmpValue($value);
            }
        }

        $normalized = [];
        foreach ($tables as $index => $row) {
            $row['snmp_index'] = $index;
            $row['onu_id'] = $row['onu_id'] ?? $row['id'] ?? $index;
            $row['onu_serial'] = $row['onu_serial'] ?? $row['serial'] ?? null;
            $row['onu_mac'] = $row['onu_mac'] ?? $row['mac'] ?? null;
            $row['pon_port'] = $row['pon_port'] ?? $row['pon'] ?? null;
            $row['onu_type'] = $row['onu_type'] ?? $row['type'] ?? null;
            $row['status'] = $row['status'] ?? ($row['online'] ?? null);
            $row['rx_power'] = $row['rx_power'] ?? $row['optical_rx'] ?? null;
            $row['tx_power'] = $row['tx_power'] ?? $row['optical_tx'] ?? null;
            $row['onu_ip'] = $row['onu_ip'] ?? $row['ip'] ?? null;
            $row['last_seen'] = $row['last_seen'] ?? null;
            $row['pppoe_username'] = $row['pppoe_username'] ?? $row['username'] ?? null;
            $normalized[] = $row;
        }
        return $normalized;
    }

    private function oidIndex(string $returnedOid, string $baseOid): ?string
    {
        $returned = ltrim($returnedOid, '.');
        $base = ltrim($baseOid, '.');
        if ($returned === $base) return '';
        $prefix = $base . '.';
        return str_starts_with($returned, $prefix) ? substr($returned, strlen($prefix)) : null;
    }

    private function cleanSnmpValue(mixed $value): mixed
    {
        if (!is_string($value)) return $value;
        $value = trim($value);
        return preg_replace('/^[A-Z0-9-]+:\\s*/i', '', $value) ?? $value;
    }

    private function storedCommunity(NetworkInventoryDevice $device): string
    {
        $raw = $device->getRawOriginal('snmp_community');
        if (!$raw) return '';
        try { return (string) Crypt::decryptString($raw); }
        catch (\Throwable) { return (string) $raw; }
    }

    private function config(NetworkInventoryDevice $device): array
    {
        $raw = $device->adapter_config;
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function fail(NetworkInventoryDevice $device, string $message): array
    {
        return [
            'ok' => false,
            'status' => 'not_ready',
            'message' => $message,
            'device' => [
                'id' => $device->id,
                'host' => $device->ip_address ?: $device->host,
                'vendor' => $device->vendor,
                'model' => $device->model,
                'transport' => 'snmp',
            ],
            'onus' => [],
            'meta' => ['read_only' => true, 'writes_performed' => false, 'oid_count' => 0],
        ];
    }
}
