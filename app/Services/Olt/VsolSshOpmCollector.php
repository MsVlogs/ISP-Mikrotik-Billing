<?php

namespace App\Services\Olt;

use App\Models\NetworkInventoryDevice;
use phpseclib3\Net\SSH2;
use Illuminate\Support\Facades\Crypt;

class VsolSshOpmCollector
{
    public function collect(NetworkInventoryDevice $device, array $baseOnus = []): array
    {
        $config = $this->config($device);
        $host = trim((string) ($device->ip_address ?: $device->host));
        $username = trim((string) data_get($config, 'ssh.username', data_get($config, 'username', $device->username)));
        $password = $this->secret(data_get($config, 'ssh.password'), $device->getRawOriginal('password'));
        $port = (int) data_get($config, 'ssh.port', $device->ssh_port ?: 22);
        $timeout = max(3, min(12, (int) data_get($config, 'ssh.timeout', 7)));

        if ($host === '') return $this->fail('VSOL SSH host/IP is not configured.');
        if ($username === '') return $this->fail('VSOL SSH username is not configured.');
        if ($password === '') return $this->fail('VSOL SSH password is not configured.');

        try {
            $ssh = new SSH2($host, $port, $timeout);
            $ssh->setTimeout($timeout);
            if (! $ssh->login($username, $password)) return $this->fail('VSOL SSH authentication failed.');

            $opmCommand = (string) data_get($config, 'opm.command', 'show onu opm-diag all');
            $opmOutput = trim((string) $ssh->exec($opmCommand));
            $opmRows = $this->parseOpm($opmOutput);
            if (! $opmRows) return $this->fail('VSOL OPM command returned no parseable ONU rows.');

            // The CLI output uses EPON0/<PON>:<ONU> with ONU numbering starting at 1,
            // while our SNMP normalized inventory may expose a zero-based ONU id.
            // Build both exact and +1 keys and prefer the CLI's 1-based key.
            $byCliKey = [];
            foreach ($baseOnus as $index => $onu) {
                $pon = trim((string) ($onu['pon_port'] ?? ''));
                $id = trim((string) ($onu['onu_id'] ?? ''));
                if ($pon === '' || $id === '') continue;
                $byCliKey[$pon.':'.$id] ??= $index;
                if (ctype_digit($id)) $byCliKey[$pon.':'.((int) $id + 1)] ??= $index;
            }

            $merged = 0;
            foreach ($opmRows as $row) {
                $baseIndex = $byCliKey[$row['cli_key']] ?? null;
                if ($baseIndex === null || ! isset($baseOnus[$baseIndex])) continue;

                $baseOnus[$baseIndex]['opm_source'] = 'vsol-cli-ssh';
                $baseOnus[$baseIndex]['temperature'] = $row['temperature'];
                $baseOnus[$baseIndex]['supply_voltage'] = $row['supply_voltage'];
                $baseOnus[$baseIndex]['tx_bias_current'] = $row['tx_bias_current'];
                $baseOnus[$baseIndex]['tx_power'] = $row['tx_power'];
                $baseOnus[$baseIndex]['rx_power'] = $row['rx_power'];
                $merged++;
            }

            return [
                'ok' => $merged > 0,
                'status' => $merged > 0 ? 'ok' : 'partial',
                'message' => $merged > 0 ? "VSOL SSH OPM populated {$merged} ONU rows." : 'VSOL SSH OPM parsed successfully, but no rows matched the live ONU index inventory.',
                'onus' => $baseOnus,
                'meta' => ['source' => 'ssh-cli', 'opm_rows' => count($opmRows), 'merged_rows' => $merged, 'read_only' => true, 'writes_performed' => false],
            ];
        } catch (\Throwable $e) {
            return $this->fail('VSOL SSH OPM read failed: '.$e->getMessage());
        }
    }

    private function parseStatus(string $output): array
    {
        $map = [];
        foreach (preg_split('/\R/', $output) as $line) {
            if (! preg_match('/EPON0\/(\d+):(\d+)\s+(online|offline|up|down)\s+([0-9A-Fa-f:.-]{12,17})(?:\s+(?:\d+))?/i', trim($line), $m)) continue;
            $key = $m[1].':'.$m[2];
            $map[$key] = ['pon' => (string) $m[1], 'onu' => (string) $m[2], 'mac' => $this->normalizeMac($m[4]), 'status' => strtolower($m[3])];
            if (preg_match('/EPON0\/\d+:\d+\s+(?:online|offline|up|down)\s+[0-9A-Fa-f:.-]{12,17}\s+(\d+)/i', trim($line), $d)) $map[$key]['distance'] = (float) $d[1];
        }
        return $map;
    }
    private function parseOpm(string $output): array
    {
        $rows = [];
        foreach (preg_split('/\R/', $output) as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
            if (! preg_match('/^(EPON0\/(\d+):(\d+))\s+([-+]?\d+(?:\.\d+)?)\s+([-+]?\d+(?:\.\d+)?)\s+([-+]?\d+(?:\.\d+)?)\s+([-+]?\d+(?:\.\d+)?)(?:\s*\wW\s*)?\s+([-+]?\d+(?:\.\d+)?)$/i', $line, $m)) continue;
            $rows[] = [
                'cli_id' => $m[1], 'cli_key' => $m[2].':'.$m[3],
                'temperature' => (float) $m[4], 'supply_voltage' => (float) $m[5],
                'tx_bias_current' => (float) $m[6], 'tx_power' => (float) $m[7], 'rx_power' => (float) $m[8],
            ];
        }
        return $rows;
    }

    private function config(NetworkInventoryDevice $device): array
    {
        $raw = $device->adapter_config;
        if (is_array($raw)) return $raw;
        if (! is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function secret(mixed $preferred, mixed $stored): string
    {
        $raw = is_string($preferred) && trim($preferred) !== '' ? $preferred : $stored;
        if (! is_string($raw) || trim($raw) === '') return '';
        try { return (string) Crypt::decryptString($raw); } catch (\Throwable) { return $raw; }
    }

    private function normalizeMac(mixed $value): string
    {
        $hex = preg_replace('/[^0-9a-f]/i', '', (string) $value) ?? '';
        if (strlen($hex) !== 12) return strtolower(trim((string) $value));
        return implode(':', str_split(strtolower($hex), 2));
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'status' => 'not_ready', 'message' => $message, 'onus' => [], 'meta' => ['source' => 'ssh-cli', 'read_only' => true, 'writes_performed' => false]];
    }
}
