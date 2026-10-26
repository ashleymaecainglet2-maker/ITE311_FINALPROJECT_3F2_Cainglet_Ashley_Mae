<?php

namespace Tests\Feature;

use App\Enums\PaymentState;
use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PayMongoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayMongoTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_gcash_checkout_for_the_outstanding_balance(): void
    {
        config([
            'services.paymongo.secret_key' => 'sk_test_123',
            'services.paymongo.base_url' => 'https://api.paymongo.com',
        ]);

        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_123',
                    'type' => 'checkout_session',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/cs_test_123',
                    ],
                ],
            ]),
        ]);

        [$subscription] = $this->subscription(1500, 500);

        $payment = app(PayMongoService::class)->createGcashCheckout($subscription);

        $this->assertSame(PaymentState::Pending, $payment->status);
        $this->assertSame('cs_test_123', $payment->provider_checkout_id);
        $this->assertSame('https://checkout.paymongo.com/cs_test_123', $payment->checkout_url);
        $this->assertSame(1000.0, (float) $payment->amount);

        Http::assertSent(function (Request $request): bool {
            $attributes = $request->data()['data']['attributes'];

            return $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
                && $attributes['payment_method_types'] === ['gcash']
                && $attributes['line_items'][0]['amount'] === 100000
                && $request->hasHeader('Authorization');
        });
    }

    public function test_webhook_marks_payment_and_subscription_as_paid(): void
    {
        config(['services.paymongo.webhook_secret' => 'whsec_test']);

        [$subscription, $payment] = $this->pendingPayment();

        $payload = json_encode([
            'data' => [
                'id' => 'evt_test',
                'type' => 'event',
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'data' => [
                        'id' => $payment->provider_checkout_id,
                        'type' => 'checkout_session',
                        'attributes' => [
                            'payments' => [[
                                'id' => 'pay_test_123',
                                'type' => 'payment',
                                'attributes' => [
                                    'amount' => 150000,
                                    'status' => 'paid',
                                    'source' => ['type' => 'gcash'],
                                ],
                            ]],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signature = "t={$timestamp},te=".hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => $signature,
        ], $payload)->assertOk()->assertJsonPath('received', true);

        $this->assertSame(PaymentState::Paid, $payment->fresh()->status);
        $this->assertSame('pay_test_123', $payment->fresh()->provider_payment_id);
        $this->assertSame(PaymentStatus::Paid, $subscription->fresh()->payment_status);
        $this->assertSame('1500.00', $subscription->fresh()->amount_paid);
    }

    public function test_webhook_rejects_an_invalid_signature(): void
    {
        config(['services.paymongo.webhook_secret' => 'whsec_test']);

        [$subscription, $payment] = $this->pendingPayment();

        $payload = json_encode([
            'data' => ['attributes' => ['type' => 'checkout_session.payment.paid', 'data' => ['id' => $payment->provider_checkout_id]]],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 't=123,te=invalid',
        ], $payload)->assertUnauthorized();

        $this->assertSame(PaymentState::Pending, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $subscription->fresh()->payment_status);
    }

    /**
     * @return array{0: Subscription, 1: Payment}
     */
    private function pendingPayment(): array
    {
        [$subscription] = $this->subscription(1500, 0);

        $payment = Payment::create([
            'gym_id' => $subscription->gym_id,
            'member_id' => $subscription->member_id,
            'subscription_id' => $subscription->id,
            'provider' => 'paymongo',
            'provider_checkout_id' => 'cs_test_webhook',
            'amount' => 1500,
            'currency' => 'PHP',
            'status' => PaymentState::Pending,
            'payment_method' => 'gcash',
            'checkout_url' => 'https://checkout.paymongo.com/cs_test_webhook',
        ]);

        return [$subscription, $payment];
    }

    /**
     * @return array{0: Subscription}
     */
    private function subscription(float $amount, float $paid): array
    {
        $gym = Gym::factory()->create();
        $plan = Plan::factory()->for($gym)->create([
            'price' => $amount,
            'duration_days' => 30,
        ]);
        $member = Member::factory()->for($gym)->create();

        $subscription = Subscription::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'starts_at' => today()->subDays(5),
            'ends_at' => today()->addDays(20),
            'amount' => $amount,
            'amount_paid' => $paid,
            'payment_status' => $paid >= $amount ? PaymentStatus::Paid : PaymentStatus::Unpaid,
            'paid_at' => $paid > 0 ? now() : null,
        ]);

        return [$subscription];
    }
}
