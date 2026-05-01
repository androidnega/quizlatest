<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SMS delivery via Arkesel — credentials come only from {@see SystemSettingsService}.
 */
class ArkeselSmsService
{
    public function __construct(
        private readonly SystemSettingsService $systemSettings,
    ) {}

    /**
     * @param  list<string>  $recipients  E.164 or provider-supported formats
     * @return array{success: bool, status?: int, body?: string}
     */
    public function send(array $recipients, string $message): array
    {
        $apiKey = $this->systemSettings->get('arkesel_api_key');
        $sender = $this->systemSettings->get('arkesel_sender_id');

        if ($apiKey === null || $apiKey === '' || $sender === null || $sender === '') {
            throw new RuntimeException('Arkesel API key and sender ID must be configured in system settings.');
        }

        $url = (string) config('services.arkesel.sms_send_url');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'api-key' => $apiKey,
        ])->timeout(15)->post($url, [
            'sender' => $sender,
            'message' => $message,
            'recipients' => array_values($recipients),
        ]);

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->body(),
        ];
    }
}
