<?php

namespace App\Services;

use App\Models\MainSiteData;
use App\Models\NetworkEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NetworkAlertNotificationService
{
    public function send(NetworkEvent $event): array
    {
        $results = [];
        $settings = MainSiteData::getValue('network_alert_channels', []);
        if (! is_array($settings)) {
            return $results;
        }

        $minSeverity = $settings['min_severity'] ?? 'warning';
        if (! $this->severityAllowed($event, $minSeverity)) {
            return $results;
        }

        $message = $this->formatMessage($event);

        if (! empty($settings['whatsapp_enabled'])) {
            $results['whatsapp'] = $this->sendWhatsApp($settings, $message);
        }

        if (! empty($settings['telegram_enabled'])) {
            $results['telegram'] = $this->sendTelegram($settings, $message);
        }

        return $results;
    }

    public function test(string $channel): array
    {
        $settings = MainSiteData::getValue('network_alert_channels', []);
        $message = "X-Link Events & Alerts test\nChannel: {$channel}\nTime: ".now()->format('d M Y H:i:s');

        return $channel === 'telegram'
            ? ['telegram' => $this->sendTelegram($settings, $message)]
            : ['whatsapp' => $this->sendWhatsApp($settings, $message)];
    }

    private function severityAllowed(NetworkEvent $event, string $minimum): bool
    {
        if ($minimum === 'device_down') {
            return strtolower((string) $event->event_type) === 'device_down';
        }
        $levels = ['info' => 1, 'warning' => 2, 'critical' => 3];
        return ($levels[$event->severity] ?? 2) >= ($levels[$minimum] ?? 2);
    }

    private function formatMessage(NetworkEvent $event): string
    {
        return "🚨 X-Link Network Alert\n"
            ."Severity: ".strtoupper($event->severity)."\n"
            ."Event: {$event->title}\n"
            ."Type: {$event->event_type}\n"
            ."Message: ".($event->message ?: 'No additional message')."\n"
            ."Occurrences: {$event->occurrences}\n"
            ."Last Seen: ".optional($event->last_seen_at ?: $event->created_at)->format('d M Y H:i:s')."\n"
            ."Status: {$event->status}";
    }

    private function sendTelegram(array $settings, string $message): array
    {
        $token = trim((string) ($settings['telegram_bot_token'] ?? ''));
        $chatId = trim((string) ($settings['telegram_chat_id'] ?? ''));
        if ($token === '' || $chatId === '') {
            return ['ok' => false, 'error' => 'Telegram Bot Token or Chat ID is not configured.'];
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);

            if (! $response->successful()) {
                Log::warning('Network alert Telegram delivery failed', ['status' => $response->status(), 'body' => $response->body()]);
            }

            return ['ok' => $response->successful(), 'status' => $response->status(), 'error' => $response->successful() ? null : $response->body()];
        } catch (\Throwable $e) {
            Log::warning('Network alert Telegram exception', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendWhatsApp(array $settings, string $message): array
    {
        $url = trim((string) ($settings['whatsapp_url'] ?? ''));
        $token = trim((string) ($settings['whatsapp_token'] ?? ''));
        $to = trim((string) ($settings['whatsapp_to'] ?? ''));
        if ($url === '' || $to === '') {
            return ['ok' => false, 'error' => 'WhatsApp API URL or recipient is not configured.'];
        }

        try {
            $request = Http::timeout(10)->acceptJson()->asJson();
            if ($token !== '') {
                $request = $request->withToken($token);
            }
            $response = $request->post($url, [
                'to' => $to,
                'message' => $message,
            ]);

            if (! $response->successful()) {
                Log::warning('Network alert WhatsApp delivery failed', ['status' => $response->status(), 'body' => $response->body()]);
            }

            return ['ok' => $response->successful(), 'status' => $response->status(), 'error' => $response->successful() ? null : $response->body()];
        } catch (\Throwable $e) {
            Log::warning('Network alert WhatsApp exception', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
