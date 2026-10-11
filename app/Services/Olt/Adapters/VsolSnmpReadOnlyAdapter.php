<?php

namespace App\Services\Olt\Adapters;

use App\Contracts\OltReadOnlyAdapter;
use App\Models\NetworkInventoryDevice;
use Illuminate\Support\Facades\Crypt;

/**
 * Read-only VSOL SNMP adapter.
 *
 * ONU identity is read from the EPON authInfo2Table. In particular, ONU ID is
 * the second table index/column, not the ordinal row number from the legacy
 * .12.1.9 table; the MAC field is never treated as a serial number.
 */
class VsolSnmpReadOnlyAdapter implements OltReadOnlyAdapter
{
    public function key(): string
    {
        return 'vsol_snmp_readonly';
    }

    public function supports(NetworkInventoryDevice $device): bool
    {
        return $device->type === 'olt'
            && strcasecmp((string) $device->vendor, 'VSOL') === 0
            && data_get($this->config($device), 'transport', 'snmp') === 'snmp';
    }

    public function read(NetworkInventoryDevice $device): array
    {
        if (!function_exists('snmp2_get') || !function_exists('snmp2_real_walk')) {
            return $this->fail($device, 'PHP SNMP extension is not installed.');
        }

        $config = $this->config($device);
        $host = trim((string) ($device->ip_address ?: $device->host));
        $community = (string) ($config['community'] ?? $this->storedCommunity($device));

        if ($host === '') {
            return $this->fail($device, 'OLT management host/IP is not configured.');
        }
        if ($community === '') {
            return $this->fail($device, 'SNMP community is not configured for the adapter.');
        }

        $profile = $this->mibProfile($device, $config);
        $timeout = min(max(100000, (int) ($config['timeout_us'] ?? 500000)), 1000000);
        $retries = min(max(0, (int) ($config['retries'] ?? 0)), 1);
        $systemOids = array_merge(
            $profile['system_oids'] ?? [
                'sysDescr' => '1.3.6.1.2.1.1.1.0',
                'sysUpTime' => '1.3.6.1.2.1.1.3.0',
            ],
            is_array($config['oids'] ?? null) ? $config['oids'] : []
        );
        $onuConfig = array_merge(
            $profile['onu_oids'] ?? [],
            is_array($config['onu_oids'] ?? null) ? $config['onu_oids'] : []
        );
        $opmOids = array_merge(
            $profile['opm_oids'] ?? [],
            is_array($config['opm_oids'] ?? null) ? $config['opm_oids'] : []
        );

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
                'mib_profile' => $profile['key'] ?? (count($onuConfig) ? 'custom_oid_configuration' : 'unsupported_model'),
                'mib_profile_label' => $profile['label'] ?? null,
                'enterprise_oid' => $profile['enterprise_oid'] ?? '1.3.6.1.4.1.37950',
                'mib_table_oid' => $profile['mib_table_oid'] ?? null,
                'oid_count' => 0,
                'failed_oids' => [],
                'onu_count' => 0,
                'onu_discovery_configured' => count($onuConfig) > 0,
            ],
        ];

        foreach ($systemOids as $name => $oid) {
            if (!is_string($name) || !is_string($oid) || !$this->validOid($oid)) {
                continue;
            }

            $value = @snmp2_get($host, $community, trim($oid), $timeout, $retries);
            $result['meta']['oid_count']++;
            if ($value === false) {
                $result['meta']['failed_oids'][] = $name;
                continue;
            }

            $result['device'][$name] = $this->cleanSnmpValue($value);
        }

        if ($onuConfig) {
            $result['onus'] = $this->discoverOnus($host, $community, $onuConfig, $timeout, $retries);
            if ($opmOids && $result['onus']) {
                $result['onus'] = $this->enrichOpticalPower($host, $community, $result['onus'], $opmOids, $timeout, $retries);
            }

            $result['meta']['onu_count'] = count($result['onus']);
            $result['meta']['onu_discovery_configured'] = true;
            $onuCount = count($result['onus']);
            $txCount = count(array_filter($result['onus'], fn ($onu) => is_numeric($onu['tx_power'] ?? null)));
            $rxCount = count(array_filter($result['onus'], fn ($onu) => is_numeric($onu['rx_power'] ?? null)));
            $macCount = count(array_filter($result['onus'], fn ($onu) => trim((string) ($onu['onu_mac'] ?? '')) !== ''));

            $result['meta']['onu_mac_count'] = $macCount;
            $result['meta']['snmp_opm_metric_count'] = $txCount + $rxCount;
            $result['meta']['opm_tx_count'] = $txCount;
            $result['meta']['opm_rx_count'] = $rxCount;
            $result['meta']['opm_tx_coverage'] = $onuCount > 0 ? round(($txCount / $onuCount) * 100, 1) : 0;
            $result['meta']['opm_rx_coverage'] = $onuCount > 0 ? round(($rxCount / $onuCount) * 100, 1) : 0;
            $result['meta']['opm_status'] = ($txCount > 0 && $rxCount > 0)
                ? (($txCount === $onuCount && $rxCount === $onuCount) ? 'ok' : 'partial')
                : 'not_ready';
            $result['meta']['opm_message'] = ($txCount + $rxCount) > 0
                ? "VSOL SNMP supplied {$macCount} ONU MACs; TX {$txCount}/{$onuCount}, RX {$rxCount}/{$onuCount}."
                : 'VSOL ONU inventory was read, but optical TX/RX metrics were unavailable in this poll.';
            $result['meta']['opm_source'] = ($txCount + $rxCount) > 0 ? 'vsol-snmp-opm' : null;
        }

        if ($result['meta']['oid_count'] === 0 && !$onuConfig) {
            $result['status'] = 'not_configured';
            $result['message'] = 'VSOL read-only adapter is ready, but no supported model MIB or custom ONU OIDs are configured.';
            return $result;
        }

        $hasDeviceData = $result['meta']['oid_count'] > 0
            && count($result['meta']['failed_oids']) < $result['meta']['oid_count'];
        $hasOnuData = count($result['onus']) > 0;
        $hasFailures = !empty($result['meta']['failed_oids']);
        $result['ok'] = $hasDeviceData || $hasOnuData;
        $result['status'] = $result['ok'] ? ($hasFailures ? 'partial' : 'ok') : 'unreachable';
        $result['message'] = $result['status'] === 'ok'
            ? 'Configured VSOL MIB OIDs and ONU discovery were read successfully; no OLT changes were made.'
            : ($result['status'] === 'partial'
                ? 'VSOL SNMP returned usable read-only data, but one or more configured system probes failed.'
                : 'VSOL SNMP read failed. No OLT changes were made.');

        return $result;
    }

    private function mibProfile(NetworkInventoryDevice $device, array $config): ?array
    {
        $profiles = (array) config('olt_vsol.snmp_profiles', []);
        $requested = trim((string) ($config['mib_profile'] ?? ''));

        if ($requested !== '' && isset($profiles[$requested])) {
            return ['key' => $requested] + $profiles[$requested];
        }

        $model = strtoupper(trim((string) $device->model));
        foreach ($profiles as $key => $profile) {
            foreach ((array) ($profile['models'] ?? []) as $candidate) {
                $candidate = strtoupper(trim((string) $candidate));
                if ($candidate !== '' && ($model === $candidate || str_contains($model, $candidate))) {
                    return ['key' => $key] + $profile;
                }
            }
        }

        return null;
    }

    private function discoverOnus(string $host, string $community, array $onuConfig, int $timeout, int $retries): array
    {
        $tables = [];

        foreach ($onuConfig as $field => $baseOid) {
            if (!is_string($field) || !is_string($baseOid) || !$this->validOid($baseOid)) {
                continue;
            }

            $walk = $this->walkWithRetry($host, $community, trim($baseOid), $timeout, $retries);
            if (!$walk) {
                continue;
            }

            foreach ($walk as $returnedOid => $value) {
                $index = $this->oidIndex((string) $returnedOid, trim($baseOid));
                if ($index === null || $index === '') {
                    continue;
                }
                $tables[$index][$field] = $this->cleanSnmpValue($value);
            }
        }

        $normalized = [];
        foreach ($tables as $index => $row) {
            $indexParts = explode('.', (string) $index);
            $row['snmp_index'] = (string) $index;
            $row['onu_id'] = $row['onu_id'] ?? end($indexParts);
            $row['pon_port'] = $row['pon_port'] ?? (count($indexParts) > 1 ? $indexParts[0] : null);
            $row['onu_serial'] = $row['onu_serial'] ?? null;
            $row['onu_mac'] = $this->normalizeMac($row['onu_mac'] ?? null);
            $row['onu_type'] = $row['onu_type'] ?? null;
            $row['onu_model'] = $row['onu_model'] ?? null;
            $row['distance_m'] = $this->normalizeMetric($row['distance_m'] ?? null);

            $rawStatus = strtolower(trim((string) ($row['status'] ?? '')));
            $row['status'] = in_array($rawStatus, ['1', 'online', 'up', 'active', 'registered', 'auth success'], true)
                ? 'online'
                : (in_array($rawStatus, ['0', '2', '4', 'offline', 'down', 'inactive', 'lost', 'deregistered', 'unregistered'], true)
                    ? 'offline'
                    : ($rawStatus !== '' ? $rawStatus : 'unknown'));

            $row['rx_power'] = null;
            $row['tx_power'] = null;
            $row['opm_source'] = null;
            $normalized[] = $row;
        }

        return $normalized;
    }

    private function enrichOpticalPower(
        string $host,
        string $community,
        array $onus,
        array $opmOids,
        int $timeout,
        int $retries
    ): array {
        $values = ['rx_power' => [], 'tx_power' => []];

        foreach (['rx_power', 'tx_power'] as $field) {
            $oid = $opmOids[$field] ?? null;
            if (!is_string($oid) || !$this->validOid($oid)) {
                continue;
            }
            $walk = $this->walkWithRetry($host, $community, trim($oid), $timeout, $retries);
            foreach ($walk as $returnedOid => $value) {
                $index = $this->oidIndex((string) $returnedOid, trim($oid));
                if ($index === null || $index === '') {
                    continue;
                }
                $values[$field][$index] = $this->normalizeOpticalPower($this->cleanSnmpValue($value));
            }
        }

        foreach ($onus as &$onu) {
            $index = (string) ($onu['snmp_index'] ?? '');
            $rx = $values['rx_power'][$index] ?? null;
            $tx = $values['tx_power'][$index] ?? null;
            $onu['rx_power'] = $rx;
            $onu['tx_power'] = $tx;
            if ($rx !== null || $tx !== null) {
                $onu['opm_source'] = 'vsol-snmp-opm';
            }
        }
        unset($onu);

        return $onus;
    }

    private function walkWithRetry(string $host, string $community, string $oid, int $timeout, int $retries): array
    {
        $baseTimeout = max(250000, min(1000000, $timeout));
        $baseRetries = max(0, min(2, $retries));

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $attemptTimeout = min(1500000, $baseTimeout * $attempt);
            $attemptRetries = min(2, $baseRetries + ($attempt - 1));
            $walk = @snmp2_real_walk($host, $community, $oid, $attemptTimeout, $attemptRetries);
            if (is_array($walk) && $walk) {
                return $walk;
            }
        }

        return [];
    }

    private function oidIndex(string $returnedOid, string $baseOid): ?string
    {
        $returned = preg_replace('/^iso\./i', '1.', $returnedOid) ?? $returnedOid;
        $returned = ltrim($returned, '.');
        $base = ltrim($baseOid, '.');

        if ($returned === $base) {
            return '';
        }

        $prefix = $base . '.';
        return str_starts_with($returned, $prefix) ? substr($returned, strlen($prefix)) : null;
    }

    private function cleanSnmpValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);
        $value = preg_replace('/^[A-Z0-9-]+:\s*/i', '', $value) ?? $value;
        return trim($value, " \t\n\r\0\x0B\"");
    }

    private function validOid(string $oid): bool
    {
        return (bool) preg_match('/^\.?\d+(?:\.\d+)*$/', trim($oid));
    }

    private function normalizeMac(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $raw = preg_replace('/^0x/i', '', trim((string) $value)) ?? trim((string) $value);
        $hex = preg_replace('/[^0-9a-f]/i', '', $raw) ?? '';
        if (strlen($hex) !== 12) {
            return null;
        }

        return strtolower(implode(':', str_split($hex, 2)));
    }

    private function normalizeOpticalPower(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/\((-?\d+(?:\.\d+)?)\s*dBm\)/i', (string) $value, $m)) {
            return (float) $m[1];
        }
        if (preg_match('/(-?\d+(?:\.\d+)?)\s*dBm/i', (string) $value, $m)) {
            return (float) $m[1];
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function normalizeMetric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (preg_match('/-?\d+(?:\.\d+)?/', (string) $value, $m)) {
            return (float) $m[0];
        }

        return null;
    }

    private function storedCommunity(NetworkInventoryDevice $device): string
    {
        $raw = $device->getRawOriginal('snmp_community');
        if (!$raw) {
            return '';
        }

        try {
            return (string) Crypt::decryptString($raw);
        } catch (\Throwable) {
            return (string) $raw;
        }
    }

    private function config(NetworkInventoryDevice $device): array
    {
        $raw = $device->adapter_config;
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

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
