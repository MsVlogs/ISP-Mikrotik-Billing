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

        $timeout = min(max(100000, (int) ($config['timeout_us'] ?? 500000)), 750000);
        $retries = min(max(0, (int) ($config['retries'] ?? 0)), 1);

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
        if (!$onuConfig) {
            $onuConfig = [
                'onu_id' => '1.3.6.1.4.1.37950.1.1.5.12.1.9.1.1',
                'pon_port' => '1.3.6.1.4.1.37950.1.1.5.12.1.9.1.2',
                'status' => '1.3.6.1.4.1.37950.1.1.5.12.1.9.1.4',
                'onu_mac' => '1.3.6.1.4.1.37950.1.1.5.12.1.9.1.5',
            ];
        }
        if ($onuConfig) {
            $result['onus'] = $this->discoverOnus($host, $community, $onuConfig, $timeout, $retries);
            $result['onus'] = $this->enrichOnus($host, $community, $result['onus'], $timeout, $retries);
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
            $position = 0;
            foreach ($walk as $returnedOid => $value) {
                $index = $this->oidIndex((string) $returnedOid, trim($baseOid));
                if ($index === null) $index = (string) $position;
                $tables[$index][$field] = $this->cleanSnmpValue($value);
                $position++;
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
            $rawStatus = strtolower(trim((string) ($row['status'] ?? ($row['online'] ?? ''))));
            $row['status'] = in_array($rawStatus, ['1', 'online', 'up', 'active', 'registered', 'auth success'], true) ? 'online' : (in_array($rawStatus, ['0', 'offline', 'down', 'inactive', 'lost', 'deregistered'], true) ? 'offline' : ($rawStatus !== '' ? $rawStatus : 'unknown'));
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
        $returned = preg_replace('/^iso\\./i', '1.', $returnedOid) ?? $returnedOid;
        $returned = ltrim($returned, '.');
        $base = ltrim($baseOid, '.');
        if ($returned === $base) return '';
        $prefix = $base . '.';
        return str_starts_with($returned, $prefix) ? substr($returned, strlen($prefix)) : null;
    }

    private function cleanSnmpValue(mixed $value): mixed
    {
        if (!is_string($value)) return $value;
        $value = trim($value);
        $value = preg_replace('/^[A-Z0-9-]+:\\s*/i', '', $value) ?? $value;
        return trim($value, '"');
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

    // VSOL enrichment helpers.
    private function enrichOnus(string $host, string $community, array $onus, int $timeout, int $retries): array
{
    $tables = [
        'auth' => [
            'onu_no' => '1.3.6.1.4.1.37950.1.1.5.12.1.12.1.3',
            'mac' => '1.3.6.1.4.1.37950.1.1.5.12.1.12.1.6',
            'type' => '1.3.6.1.4.1.37950.1.1.5.12.1.12.1.7',
        ],
        'sn' => [
            'vendor' => '1.3.6.1.4.1.37950.1.1.5.12.2.1.2.1.3',
            'model' => '1.3.6.1.4.1.37950.1.1.5.12.2.1.2.1.4',
            'onu_id' => '1.3.6.1.4.1.37950.1.1.5.12.2.1.2.1.5',
            'hw' => '1.3.6.1.4.1.37950.1.1.5.12.2.1.2.1.6',
            'sw' => '1.3.6.1.4.1.37950.1.1.5.12.2.1.2.1.7',
        ],
        'opm' => [
            'tx' => '1.3.6.1.4.1.37950.1.1.5.12.2.1.8.1.6',
            'rx' => '1.3.6.1.4.1.37950.1.1.5.12.2.1.8.1.7',
        ],
    ];
    $lookup = [];
    foreach ($tables as $group => $fields) {
        foreach ($fields as $field => $base) {
            $walk = @snmp2_real_walk($host, $community, $base, $timeout, $retries);
            if (!is_array($walk)) continue;
            foreach ($walk as $oid => $value) {
                $idx = $this->oidIndex((string) $oid, $base);
                if ($idx === null) continue;
                $parts = array_values(array_filter(explode('.', $idx), 'strlen'));
                $key = count($parts) >= 2 ? $parts[count($parts)-2].'.'.$parts[count($parts)-1] : (string) ($parts[0] ?? '');
                if (in_array($group, ['sn','opm'], true) && count($parts) >= 2 && ctype_digit((string) $parts[count($parts)-1])) {
                    $key = $parts[count($parts)-2].'.'.max(0, ((int) $parts[count($parts)-1]) - 1);
                }
                $lookup[$group][$key][$field] = $this->cleanSnmpValue($value);
            }
        }
    }
    foreach ($onus as &$onu) {
        $pon = (string) ($onu['pon_port'] ?? '');
        $id = (string) ($onu['onu_id'] ?? '');
        $key = $pon !== '' ? $pon.'.'.$id : $id;
        $shiftedKey = $pon !== '' && ctype_digit($id) ? $pon.'.'.((int) $id + 1) : null;
        $snKey = $shiftedKey ?: $key;
        $snExtra = [];
        $targetMac = $this->normalizeMac($onu['onu_mac'] ?? '');
        if ($targetMac !== '') {
            foreach ($lookup['sn'] ?? [] as $candidateKey => $candidate) {
                if ($this->normalizeMac($candidate['onu_id'] ?? '') === $targetMac) {
                    $snKey = $candidateKey;
                    $snExtra = $candidate;
                    break;
                }
            }
        }
        foreach (['auth','sn','opm'] as $group) {
            $extra = $group === 'sn'
                ? ($snExtra ?: ($lookup[$group][$snKey] ?? []))
                : ($group === 'opm' ? ($lookup[$group][$snKey] ?? $lookup[$group][$key] ?? []) : ($lookup[$group][$key] ?? $lookup[$group][$id] ?? []));
            if ($group === 'auth') {
                $onu['onu_type'] = $onu['onu_type'] ?? ($extra['type'] ?? null);
                $onu['onu_mac'] = $onu['onu_mac'] ?? ($extra['mac'] ?? null);
            } elseif ($group === 'sn') {
                $onu['onu_vendor'] = $extra['vendor'] ?? null;
                $onu['onu_model'] = $extra['model'] ?? null;
                $onu['onu_serial'] = $extra['onu_id'] ?? ($onu['onu_serial'] ?? null);
                $onu['hardware_version'] = $extra['hw'] ?? null;
                $onu['software_version'] = $extra['sw'] ?? null;
            } else {
                $onu['tx_power'] = $this->normalizeOpticalPower($extra['tx'] ?? ($onu['tx_power'] ?? null));
                $onu['rx_power'] = $this->normalizeOpticalPower($extra['rx'] ?? ($onu['rx_power'] ?? null));
            }
        }
    }
    unset($onu);
        return $onus;
    }

    private function normalizeOpticalPower(mixed $value): mixed
    {
        if ($value === null || $value === '') return null;
        if (preg_match('/\((-?\d+(?:\.\d+)?)\s*dBm\)/i', (string) $value, $m)) return (float) $m[1];
        return is_numeric($value) ? (float) $value : null;
    }

    private function normalizeMac(mixed $value): string
    {
        $raw = preg_replace('/^0x/i', '', (string) $value) ?? (string) $value;
        $hex = preg_replace('/[^0-9a-f]/i', '', $raw) ?? '';
        if (strlen($hex) !== 12) return '';
        return strtolower(implode(':', str_split($hex, 2)));
    }
}
