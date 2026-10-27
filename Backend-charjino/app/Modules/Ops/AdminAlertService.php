<?php

namespace App\Modules\Ops;

use App\Models\AdminAlert;
use App\Support\SafeLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class AdminAlertService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function open(string $type, string $dedupeKey, array $payload = []): AdminAlert
    {
        $existing = AdminAlert::query()
            ->where('dedupe_key', $dedupeKey)
            ->whereNull('resolved_at')
            ->first();
        if ($existing) {
            $last = $existing->notified_at ?? $existing->opened_at;
            if ($last && $last->gt(now()->subMinutes(30))) {
                return $existing;
            }
            $existing->payload = $payload;
            $existing->notified_at = now();
            $existing->save();
            $this->notify($existing);

            return $existing;
        }

        $alert = AdminAlert::query()->create([
            'type' => $type,
            'dedupe_key' => $dedupeKey,
            'payload' => $payload,
            'opened_at' => now(),
            'notified_at' => now(),
        ]);
        $this->notify($alert);

        return $alert;
    }

    public function resolve(string $dedupeKey): void
    {
        AdminAlert::query()
            ->where('dedupe_key', $dedupeKey)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    private function notify(AdminAlert $alert): void
    {
        $payload = is_array($alert->payload) ? $alert->payload : [];
        $summary = (string) ($payload['summary'] ?? $alert->type);
        SafeLog::warning('admin.alert', [
            'type' => $alert->type,
            'dedupe' => $alert->dedupe_key,
            'summary' => $summary,
        ]);
        $email = trim((string) config('charjino.alerts.email'));
        if ($email !== '') {
            try {
                Mail::raw($summary."\n".json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), function ($message) use ($email, $alert): void {
                    $message->to($email)->subject('شارژینو: '.$alert->type);
                });
            } catch (\Throwable $e) {
                SafeLog::warning('admin.alert.email_failed', ['message' => $e->getMessage()]);
            }
        }
        $token = trim((string) config('charjino.alerts.telegram_bot_token'));
        $chat = trim((string) config('charjino.alerts.telegram_chat_id'));
        if ($token !== '' && $chat !== '') {
            try {
                Http::timeout(8)->post('https://api.telegram.org/bot'.$token.'/sendMessage', [
                    'chat_id' => $chat,
                    'text' => $summary,
                ]);
            } catch (\Throwable $e) {
                SafeLog::warning('admin.alert.telegram_failed', ['message' => $e->getMessage()]);
            }
        }
    }
}
