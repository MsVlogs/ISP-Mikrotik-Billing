<?php

namespace App\Livewire;

use App\Http\Controllers\MikrotikController;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use Illuminate\Support\Str;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MikrotikClients extends Component
{
    public string $router = '';
    public string $protocol = 'PPPOE';
    public string $profile = '';
    public string $userType = 'Unique';
    public string $search = '';
    public int $perPage = 100;

    public function render()
    {
        abort_unless(hasAccess(['Super Admin'], ['all-customer']), 403);

        $routers = RouterList::query()->orderBy('router_name')->get();
        $profiles = PPPSecrets::query()->when($this->router, fn ($q) => $q->where('router_name', $this->router))
            ->whereNotNull('profile')->where('profile', '!=', '')->distinct()->orderBy('profile')->pluck('profile');

        $clients = PPPSecrets::query()->with('customer')
            ->when($this->router, fn ($q) => $q->where('router_name', $this->router))
            ->when($this->protocol, fn ($q) => $q->whereRaw('upper(service) = ?', [strtoupper($this->protocol)]))
            ->when($this->profile, fn ($q) => $q->where('profile', $this->profile))
            ->when($this->userType === 'Unique', fn ($q) => $q->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['duplicate', 'disabled_duplicate']);
            }))
            ->when($this->search, function ($q) {
                $s = '%'.addcslashes($this->search, '%_').'%';
                $q->where(function ($q) use ($s) {
                    $q->where('username', 'like', $s)->orWhere('caller_id', 'like', $s)->orWhere('router_name', 'like', $s);
                });
            })
            ->orderBy('username')->paginate($this->perPage);

        return view('livewire.mikrotik-clients', compact('routers', 'profiles', 'clients'))->layout('layouts.app');
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['router', 'profile', 'search']);
        $this->protocol = 'PPPOE';
        $this->userType = 'Unique';
    }

    public function toggle(int $id): void
    {
        abort_unless(hasAccess(['Super Admin'], ['enable-pending-customer']), 403);
        $secret = PPPSecrets::findOrFail($id);
        $disabled = in_array(strtolower((string) $secret->status), ['disabled', 'inactive'], true);
        app(MikrotikController::class)->togglePPPSecret($secret->customer?->customer_unique_id ?? $secret->username, $secret->router_name, $secret->username, $disabled ? 'enable' : 'disable');
        $secret->status = $disabled ? 'enabled' : 'disabled';
        $secret->save();
    }

    public function exportExcel()
    {
        $rows = $this->exportRows();
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $headers = ['Name','Password','Service','Profile','Caller ID','Server Name','Logout Time','User Status','Branch'];
        $ws->fromArray([$headers, ...$rows], null, 'A1');
        foreach (range('A', 'I') as $col) $ws->getColumnDimension($col)->setAutoSize(true);
        $file = 'mikrotik-clients-'.now()->format('Ymd-His').'.xlsx';
        return response()->streamDownload(function () use ($sheet) { (new Xlsx($sheet))->save('php://output'); }, $file);
    }

    public function exportClientList()
    {
        return $this->csvDownload('client-list');
    }

    public function exportMacReseller()
    {
        return $this->csvDownload('macreseller');
    }

    protected function exportRows(): array
    {
        $query = PPPSecrets::query()->with('customer')
            ->when($this->router, fn ($q) => $q->where('router_name', $this->router))
            ->when($this->protocol, fn ($q) => $q->whereRaw('upper(service) = ?', [strtoupper($this->protocol)]))
            ->when($this->profile, fn ($q) => $q->where('profile', $this->profile))
            ->when($this->search, fn ($q) => $q->where('username', 'like', '%'.$this->search.'%'))
            ->orderBy('username')->get();

        return $query->map(fn ($c) => [$c->username, $c->password, $c->service, $c->profile, $c->caller_id ?: 'N/A', $c->router_name, optional($c->last_logged_out)?->format('d/m/Y h:i A'), $c->status ?: 'Unknown', $c->customer?->branch ?: 'N/A'])->all();
    }

    protected function csvDownload(string $type)
    {
        $rows = $this->exportRows();
        $file = "mikrotik-{$type}-".now()->format('Ymd-His').'.csv';
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name','Password','Service','Profile','Caller ID','Server Name','Logout Time','User Status','Branch']);
            foreach ($rows as $row) fputcsv($out, $row);
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
