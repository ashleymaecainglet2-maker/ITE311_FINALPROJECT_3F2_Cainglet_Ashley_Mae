<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SemaphoreService
{
    private ?string $apiKey;

    private string $baseUrl;

    private bool $enabled;

    public function __construct(?string $apiKey = null, string $baseUrl = 'https://api.semaphore.co/api/v4', bool $enabled = false)
    {
        $this->apiKey = $apiKey ?? config('services.semaphore.api_key');
        $this->baseUrl = rtrim(config('services.semaphore.base_url') ?: $baseUrl, '/');
        $this->enabled = (bool) config('services.semaphore.enabled', false);
    }

    public function send(string $number, string $message): bool
    {
        if (! $this->enabled) {
            Log::info('Semaphore SMS disabled; message not sent.', [
                'number' => $this->normalizeNumber($number),
                'message' => $message,
            ]);

            return false;
        }

        if (blank($this->apiKey)) {
            throw new RuntimeException('SEMAPHORE_API_KEY is not configured.');
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post("{$this->baseUrl}/messages", [
                'apikey' => $this->apiKey,
                'number' => $this->normalizeNumber($number),
                'message' => $message,
                'sendername' => config('services.semaphore.sender_name'),
            ]);

        if ($response->failed()) {
            Log::warning('Semaphore SMS failed.', [
                'number' => $this->normalizeNumber($number),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Normalize a Philippine mobile number to the 639XXXXXXXXX format.
     */
    public function normalizeNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (str_starts_with($digits, '0')) {
            return '63'.substr($digits, 1);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '63'.$digits;
        }

        return $digits;
    }
}
