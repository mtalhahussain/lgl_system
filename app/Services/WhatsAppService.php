<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Meta WhatsApp Cloud API template sender (reused from the gym project). */
class WhatsAppService
{
    public function configured(): bool
    {
        return (string) config('whatsapp.token') !== '' && (string) config('whatsapp.phone_number_id') !== '';
    }

    /**
     * Sends an approved template with positional body parameters ({{1}}, {{2}}, …).
     *
     * @return string Meta message id (wamid…)
     *
     * @throws RuntimeException when not configured or Meta rejects the request
     */
    public function sendTemplate(string $to, string $template, string $language, array $bodyParams): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('WhatsApp is not configured. Set WHATSAPP_TOKEN and WHATSAPP_PHONE_NUMBER_ID in .env.');
        }
        $payload = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
            'template' => ['name' => $template, 'language' => ['code' => $language]]];
        if ($bodyParams) {
            $payload['template']['components'] = [['type' => 'body',
                'parameters' => array_map(fn ($value) => ['type' => 'text', 'text' => (string) $value], array_values($bodyParams))]];
        }
        $url = rtrim((string) config('whatsapp.base_url'), '/').'/'.config('whatsapp.version').'/'.config('whatsapp.phone_number_id').'/messages';
        try {
            $response = Http::withToken((string) config('whatsapp.token'))->acceptJson()->asJson()
                ->timeout((int) config('whatsapp.timeout', 15))->post($url, $payload);
        } catch (\Throwable $exception) {
            throw new RuntimeException('WhatsApp API not reachable: '.$exception->getMessage());
        }
        if (! $response->successful()) {
            $error = $response->json('error');
            throw new RuntimeException('WhatsApp API error '.$response->status().': '
                .(is_array($error) ? trim(($error['code'] ?? '').' '.($error['message'] ?? '')) : mb_substr($response->body(), 0, 500)));
        }
        $id = $response->json('messages.0.id');
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('WhatsApp API returned no message id.');
        }

        return $id;
    }

    /** Digits in international form without "+", or null when the number cannot be a WhatsApp number. */
    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = config('whatsapp.default_country_code', '92').substr($digits, 1);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            $digits = config('whatsapp.default_country_code', '92').$digits;  // 3001234567
        }

        return preg_match('/^[1-9]\d{9,14}$/D', $digits) ? $digits : null;
    }
}
