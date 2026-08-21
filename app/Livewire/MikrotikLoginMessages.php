<?php

namespace App\Livewire;

use App\Http\Controllers\MikrotikController;
use App\Models\MikrotikLog;
use App\Models\RouterList;
use Livewire\Component;
use Livewire\WithPagination;

class MikrotikLoginMessages extends Component
{
    use WithPagination;

    public string $router = '';
    public string $search = '';

    public function sync(): void
    {
        if (! $this->router) {
            return;
        }

        try {
            $controller = app(MikrotikController::class);
            $controller->storeRouterLogs(
                $this->router,
                $controller->getRouterLogs($this->router, 200)
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function classifyEvent(string $message, string $topics = ''): string
    {
        $text = strtolower(trim($topics.' '.$message));

        if (preg_match('/auth(?:entication)?[^\n]*(?:fail|invalid|denied|reject)/i', $text)) {
            return 'auth_failed';
        }

        if (preg_match('/(?:logged|log|user)[^\n]*(?:out|logout)|(?:disconnected|terminated)/i', $text)) {
            return 'logout';
        }

        if (preg_match('/(?:logged|log|user)[^\n]*(?:in|login)|(?:connected|authenticated)/i', $text)) {
            return 'login';
        }

        return 'other';
    }

    public function render()
    {
        $base = MikrotikLog::query()
            ->when($this->router, fn ($query) => $query->where('router_name', $this->router));

        $logs = (clone $base)
            ->when($this->search, fn ($query) => $query->where('message', 'like', '%'.$this->search.'%'))
            ->latest()
            ->paginate(50);

        foreach ($logs->items() as $log) {
            $log->setAttribute('event_type', self::classifyEvent($log->message ?? '', $log->topics ?? ''));
        }

        $login = (clone $base)->where(function ($query) {
            $query->where('message', 'like', '%logged in%')
                ->orWhere('message', 'like', '%login%')
                ->orWhere('message', 'like', '%authenticated%');
        })->count();

        $logout = (clone $base)->where(function ($query) {
            $query->where('message', 'like', '%logged out%')
                ->orWhere('message', 'like', '%logout%')
                ->orWhere('message', 'like', '%disconnected%');
        })->count();

        $failed = (clone $base)->where(function ($query) {
            $query->where('message', 'like', '%authentication%fail%')
                ->orWhere('message', 'like', '%auth%fail%')
                ->orWhere('message', 'like', '%invalid%')
                ->orWhere('message', 'like', '%denied%');
        })->count();

        return view('livewire.mikrotik-login-messages', [
            'logs' => $logs,
            'routers' => RouterList::where('action', 'connected')->pluck('router_name'),
            'login' => $login,
            'logout' => $logout,
            'failed' => $failed,
        ])->layout('layouts.app');
    }
}
