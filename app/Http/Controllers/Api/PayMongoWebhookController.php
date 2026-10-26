<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PayMongoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookController extends Controller
{
    public function __construct(private readonly PayMongoService $payMongo) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Paymongo-Signature');

        if (filled(config('services.paymongo.webhook_secret'))) {
            if (! $this->payMongo->verifyWebhookSignature($payload, $signature)) {
                Log::warning('Rejected PayMongo webhook with invalid signature.');

                return response()->json(['message' => 'Invalid signature.'], 401);
            }
        } else {
            Log::warning('PayMongo webhook received but PAYMONGO_WEBHOOK_SECRET is not configured.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        $eventType = data_get($event, 'data.attributes.type');
        $resource = data_get($event, 'data.attributes.data');

        if (! is_array($resource)) {
            return response()->json(['received' => true]);
        }

        try {
            match ($eventType) {
                'checkout_session.payment.paid' => $this->handleCheckoutPaid($resource, $event),
                'payment.paid' => $this->handlePaymentPaid($resource, $event),
                'payment.failed' => $this->handlePaymentFailed($resource, $event),
                default => Log::info('Unhandled PayMongo webhook event.', ['type' => $eventType]),
            };
        } catch (\Throwable $exception) {
            Log::error('PayMongo webhook processing failed.', [
                'type' => $eventType,
                'error' => $exception->getMessage(),
                'payload' => $payload,
            ]);

            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $session
     * @param  array<string, mixed>  $event
     */
    private function handleCheckoutPaid(array $session, array $event): void
    {
        $checkoutId = $session['id'] ?? null;

        if (! $checkoutId) {
            return;
        }

        $payment = Payment::query()
            ->where('provider_checkout_id', $checkoutId)
            ->first();

        if (! $payment) {
            Log::warning('PayMongo checkout payment received for unknown session.', [
                'checkout_id' => $checkoutId,
            ]);

            return;
        }

        $providerPayment = collect(data_get($session, 'attributes.payments', []))
            ->firstWhere('status', 'paid')
            ?? data_get($session, 'attributes.payments.0');

        $amount = $this->amountFromCentavos(data_get($providerPayment, 'attributes.amount'));

        $payment->markPaid(
            amount: $amount ?? (float) $payment->amount,
            providerPaymentId: data_get($providerPayment, 'id'),
            method: data_get($providerPayment, 'attributes.source.type', 'gcash'),
            payload: $event,
        );
    }

    /**
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $event
     */
    private function handlePaymentPaid(array $resource, array $event): void
    {
        $paymentId = data_get($resource, 'attributes.metadata.payment_id');
        $reference = data_get($resource, 'attributes.metadata.reference_number')
            ?? data_get($resource, 'attributes.reference_number');

        $payment = Payment::query()
            ->when($paymentId, fn ($query) => $query->whereKey($paymentId))
            ->when(! $paymentId && $reference, fn ($query) => $query->where('reference_number', $reference))
            ->when(! $paymentId && ! $reference, fn ($query) => $query->where('provider_payment_id', $resource['id'] ?? ''))
            ->first();

        if (! $payment) {
            Log::warning('PayMongo payment.paid event could not be matched.', [
                'payment_id' => $resource['id'] ?? null,
                'reference' => $reference,
            ]);

            return;
        }

        $payment->markPaid(
            amount: $this->amountFromCentavos(data_get($resource, 'attributes.amount')) ?? (float) $payment->amount,
            providerPaymentId: $resource['id'] ?? null,
            method: data_get($resource, 'attributes.source.type', 'gcash'),
            payload: $event,
        );
    }

    /**
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $event
     */
    private function handlePaymentFailed(array $resource, array $event): void
    {
        $payment = Payment::query()
            ->where('provider_payment_id', $resource['id'] ?? '')
            ->first();

        $payment?->markFailed('failed', $event);
    }

    private function amountFromCentavos(mixed $amount): ?float
    {
        return is_numeric($amount) ? round((float) $amount / 100, 2) : null;
    }
}
