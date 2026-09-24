<?php
namespace App\Services\Olt;

use App\Models\NetworkInventoryDevice;
use Illuminate\Support\Facades\Crypt;
use phpseclib3\Net\SSH2;
use RuntimeException;

class VsolCliProvisioningService
{
    private SSH2 $ssh;
    private array $config;

    public function connect(NetworkInventoryDevice $device): void
    {
        $this->config = $this->config($device);
        $host = (string)($device->ip_address ?: $device->host);
        if ($host === '') throw new RuntimeException('OLT management IP/host is not configured.');
        if (!$device->provisioning_enabled) throw new RuntimeException('OLT provisioning is disabled.');
        $transport = strtolower((string)($this->config['write_transport'] ?? ($device->ssh_enabled ? 'ssh' : 'telnet')));
        if ($transport !== 'ssh') throw new RuntimeException('Only SSH provisioning is enabled by this service; configure VSOL SSH before enabling writes.');
        $username = $this->secret($device, 'username');
        $password = $this->secret($device, 'password');
        if ($username === '' || $password === '') throw new RuntimeException('OLT provisioning credentials are not configured.');
        $port = (int)($device->ssh_port ?: 22);
        $this->ssh = new SSH2($host, $port, max(3, (int)($device->cli_timeout ?: 10)));
        if (!$this->ssh->login($username, $password)) throw new RuntimeException('VSOL SSH login failed.');
    }

    public function authorizeByMac(string $pon, string $mac): string
    {
        $this->validatePon($pon); $this->validateMac($mac);
        return $this->run($this->command('authorize_mac', ['pon'=>$pon,'mac'=>$mac]));
    }

    public function removeByMac(string $pon, string $mac): string
    {
        $this->validatePon($pon); $this->validateMac($mac);
        return $this->run($this->command('remove_mac', ['pon'=>$pon,'mac'=>$mac]));
    }

    public function configurePppoe(string $pon, string $onu, string $username, string $password): string
    {
        $this->validatePon($pon); $this->validateOnu($onu);
        if ($username === '' || strlen($username) > 128 || strlen($password) > 128) throw new RuntimeException('Invalid PPPoE credentials.');
        $commands = $this->commands('pppoe', compact('pon','onu','username','password'));
        return $this->runMany($commands);
    }

    public function configureStaticIp(string $pon, string $onu, string $ip, string $netmask, string $gateway): string
    {
        $this->validatePon($pon); $this->validateOnu($onu);
        foreach ([$ip,$gateway] as $value) if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) throw new RuntimeException('Invalid IPv4 address.');
        if (!filter_var($netmask, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) throw new RuntimeException('Invalid IPv4 netmask.');
        return $this->runMany($this->commands('static_ip', compact('pon','onu','ip','netmask','gateway')));
    }

    public function disableOnu(string $pon, string $onu): string
    {
        $this->validatePon($pon); $this->validateOnu($onu);
        return $this->run($this->command('disable_onu', compact('pon','onu')));
    }

    public function enableOnu(string $pon, string $onu): string
    {
        $this->validatePon($pon); $this->validateOnu($onu);
        return $this->run($this->command('enable_onu', compact('pon','onu')));
    }

    public function save(): string
    {
        return $this->run((string)($this->config['commands']['save'] ?? 'write'));
    }

    private function commands(string $type, array $vars): array
    {
        $configured = data_get($this->config, 'commands.'.$type);
        if (is_array($configured) && $configured) return array_map(fn($c)=>(string)$this->render($c,$vars), $configured);
        if ($type === 'pppoe') return [
            'interface epon '.$vars['pon'],
            'onu '.$vars['onu'].' pri wan_conn add route',
            'onu '.$vars['onu'].' pri wan_conn index 1 route internet nat enable mtu 1492 pppoe proxy disable user '.$this->quote($vars['username']).' pwd '.$this->quote($vars['password']).' mode auto',
            'onu '.$vars['onu'].' pri wan_conn commit',
            'exit',
        ];
        if ($type === 'static_ip') return [
            'interface epon '.$vars['pon'],
            'onu '.$vars['onu'].' pri wan_conn add route',
            'onu '.$vars['onu'].' pri wan_conn index 1 route internet nat enable ip '.$vars['ip'].' netmask '.$vars['netmask'].' gateway '.$vars['gateway'].' mode manual',
            'onu '.$vars['onu'].' pri wan_conn commit',
            'exit',
        ];
        throw new RuntimeException('No safe VSOL command template configured for '.$type.'. Configure adapter_config.commands.'.$type.' for this exact model.');
    }

    private function command(string $type, array $vars): string
    {
        $configured = data_get($this->config, 'commands.'.$type);
        if (is_array($configured)) $configured = $configured[0] ?? '';
        if (!$configured) {
            if ($type === 'authorize_mac') return 'interface epon '.$vars['pon'].'; onu-auth mode mac; onu mac-auth add '.$vars['mac'].'; exit';
            if ($type === 'remove_mac') return 'interface epon '.$vars['pon'].'; onu mac-auth del '.$vars['mac'].'; exit';
            if ($type === 'disable_onu') return 'interface epon '.$vars['pon'].'; onu '.$vars['onu'].' disable; exit';
            if ($type === 'enable_onu') return 'interface epon '.$vars['pon'].'; onu '.$vars['onu'].' enable; exit';
        }
        if (!$configured) throw new RuntimeException('No safe VSOL command template configured for '.$type.'.');
        return (string)$this->render($configured,$vars);
    }

    private function runMany(array $commands): string { return $this->run(implode("\n", $commands)); }
    private function run(string $commands): string
    {
        $this->ssh->write($commands."\n");
        $out = $this->ssh->read('/(?:#|>)\s*$/m');
        return trim((string)$out);
    }
    private function render(string $template, array $vars): string
    {
        foreach ($vars as $key=>$value) $template = str_replace('{{'.$key.'}}', $this->quote((string)$value), $template);
        return $template;
    }
    private function quote(string $value): string
    {
        if (preg_match('/[\r\n;`$]/', $value)) throw new RuntimeException('Unsafe command parameter rejected.');
        return $value;
    }
    private function validatePon(string $pon): void { if (!preg_match('/^\d+\/\d+$/',$pon)) throw new RuntimeException('PON must use slot/port format.'); }
    private function validateOnu(string $onu): void { if (!preg_match('/^\d+$/',$onu)) throw new RuntimeException('ONU ID must be numeric.'); }
    private function validateMac(string $mac): void { if (!preg_match('/^(?:[0-9a-f]{2}:){5}[0-9a-f]{2}$/i',$mac)) throw new RuntimeException('Invalid ONU MAC address.'); }
    private function secret(NetworkInventoryDevice $device, string $field): string
    {
        $raw = $device->getRawOriginal($field); if (!$raw) return '';
        try { return (string)Crypt::decryptString($raw); } catch (\Throwable) { return (string)$raw; }
    }
    private function config(NetworkInventoryDevice $device): array
    {
        $raw=$device->adapter_config; if (is_array($raw)) return $raw;
        $decoded=json_decode((string)$raw,true); return is_array($decoded)?$decoded:[];
    }
}
