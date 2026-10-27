<?php

namespace App\Services;

use App\Enums\PaymentState;
use App\Exceptions\PayMongoException;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PayMongoService
{
    private ?string $secretKey;

    private string $baseUrl;

    public function __construct(?string $secretKey = null, string $baseUrl = 'https://api.paymongo.com')
    {
        $this->secretKey = $secretKey ?? config('services.paymongo.secret_key');
        $this->baseUrl = rtrim(config('services.paymongo.base_url') ?: $baseUrl, '/');
    }

    /**
     * Create a pending payment and a GCash checkout session for a subscription.
     */
    public function createGcashCheckout(Subscription $subscription): Payment
    {
        $this->ensureConfigured();

        $amountDue = $subscription->amountDue();

        if ($amountDue <= 0) {
            throw new InvalidArgumentException('This subscription has no outstanding balance.');
        }

        $subscription->loadMissing(['member', 'plan', 'gym']);

        $payment = Payment::create([
            'gym_id' => $subscription->gym_id,
            'member_id' => $subscription->member_id,
            'subscription_id' => $subscription->id,
            'provider' => 'paymongo',
            'amount' => $amountDue,
            'currency' => 'PHP',
            'status' => PaymentState::Pending,
            'payment_method' => 'gcash',
        ]);

        try {
            $session = $this->createCheckoutSession($subscription, $payment);
        } catch (\Throwable $exception) {
            $payment->update([
                'status' => PaymentState::Failed,
                'payload' => ['error' => $exception->getMessage()],
            ]);

            Log::error('PayMongo checkout creation failed', [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $payment->update([
            'provider_checkout_id' => $session['id'] ?? null,
            'checkout_url' => $session['attributes']['checkout_url'] ?? null,
            'payload' => $session,
        ]);

        return $payment->refresh();
    }

    /**
     * @return array<string, mixed> The checkout session data returned by PayMongo.
     */
    public function createCheckoutSession(Subscription $subscription, Payment $payment): array
    {
        $this->ensureConfigured();

        $amount = (int) round((float) $payment->amount * 100);

        if ($amount < 100) {
            throw new InvalidArgumentException('PayMongo requires a minimum amount of PHP 1.00.');
        }

        $member = $subscription->member;
        $plan = $subscription->plan;
        $reference = $payment->reference_number;

        $payload = [
            'data' => [
                'attributes' => [
                    'send_email_receipt' => filled($member->email),
                    'show_description' => true,
                    'show_line_items' => true,
                    'description' => mb_substr(
                        "FitLedger dues - {$plan->name} ({$member->name})",
                        0,
                        255
                    ),
                    'line_items' => [[
                        'currency' => 'PHP',
                        'amount' => $amount,
                        'name' => mb_substr($plan->name, 0, 255),
                        'quantity' => 1,
                        'description' => mb_substr(
                            "Subscription {$subscription->starts_at->format('M j')} - {$subscription->ends_at->format('M j, Y')}",
                            0,
                            255
                        ),
                    ]],
                    'payment_method_types' => ['gcash'],
                    'success_url' => $this->paymentResultUrl('payments.success', $reference),
                    'cancel_url' => $this->paymentResultUrl('payments.cancelled', $reference),
                    'reference_number' => $reference,
                    'metadata' => [
                        'gym_id' => (string) $subscription->gym_id,
                        'member_id' => (string) $subscription->member_id,
                        'subscription_id' => (string) $subscription->id,
                        'payment_id' => (string) $payment->id,
                        'payment_channel' => 'gcash',
                        'initiated_at' => now()->toIso8601String(),
                    ],
                ],
            ],
        ];

        Log::info('Initiating PayMongo GCash checkout session', [
            'reference_number' => $reference,
            'subscription_id' => $subscription->id,
            'amount_php' => $payment->amount,
        ]);

        $response = $this->request('post', '/v1/checkout_sessions', $payload);

        return $response->json('data') ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $checkoutSessionId): array
    {
        $this->ensureConfigured();

        return $this->request('get', "/v1/checkout_sessions/{$checkoutSessionId}")->json('data') ?? [];
    }

    /**
     * Validate the `Paymongo-Signature` header. Signature is HMAC-SHA256 of
     * "{timestamp}.{rawRequestBody}" using the webhook secret.
     */
    public function verifyWebhookSignature(string $payload, ?string $signatureHeader): bool
    {
        $secret = config('services.paymongo.webhook_secret');

        if (blank($secret) || blank($signatureHeader)) {
            return false;
        }

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

                return [$key => $value];
            });

        $timestamp = $parts->get('t');
        $testSignature = $parts->get('te');
        $liveSignature = $parts->get('li');

        if (blank($timestamp) || (blank($testSignature) && blank($liveSignature))) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return (! blank($testSignature) && hash_equals($expected, (string) $testSignature))
            || (! blank($liveSignature) && hash_equals($expected, (string) $liveSignature));
    }

    private function request(string $method, string $path, array $payload = []): Response
    {
        $request = Http::baseUrl($this->baseUrl)
            ->withBasicAuth((string) $this->secretKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(20);

        if ($method === 'get') {
            $request = $request->retry(2, 250);
        }

        $response = $request->{$method}($path, $payload);

        if ($response->failed()) {
            throw PayMongoException::fromResponse($response);
        }

        return $response;
    }

    private function ensureConfigured(): void
    {
        if (blank($this->secretKey)) {
            throw PayMongoException::missingApiKey();
        }
    }

    private function paymentResultUrl(string $route, string $reference): string
    {
        $url = config("services.paymongo.{$this->routeConfigKey($route)}") ?: route($route);
        $url = str_contains($url, '://') ? $url : url($url);
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query(['ref' => $reference]);
    }

    private function routeConfigKey(string $route): string
    {
        return $route === 'payments.success' ? 'success_url' : 'cancel_url';
    }
}
