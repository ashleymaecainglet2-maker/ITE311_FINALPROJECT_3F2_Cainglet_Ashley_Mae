<?php

namespace App\Models;

use App\Enums\PaymentState;
use App\Models\Concerns\BelongsToGym;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToGym, HasFactory;

    protected $fillable = [
        'gym_id',
        'member_id',
        'subscription_id',
        'provider',
        'provider_checkout_id',
        'provider_payment_id',
        'reference_number',
        'amount',
        'currency',
        'status',
        'payment_method',
        'checkout_url',
        'paid_at',
        'payload',
    ];

    protected $attributes = [
        'provider' => 'paymongo',
        'status' => 'pending',
        'currency' => 'PHP',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentState::class,
            'paid_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->reference_number ??= static::generateGcashReference();
        });
    }

    public static function generateGcashReference(): string
    {
        return 'GCASH-FL-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }

    public function isGcash(): bool
    {
        return strtolower($this->payment_method ?? '') === 'gcash';
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Mark the checkout as paid exactly once and apply the amount to its subscription.
     */
    public function markPaid(
        ?float $amount = null,
        ?string $providerPaymentId = null,
        ?string $method = 'gcash',
        ?array $payload = null,
    ): void {
        if ($this->status === PaymentState::Paid) {
            return;
        }

        DB::transaction(function () use ($amount, $providerPaymentId, $method, $payload): void {
            $this->status = PaymentState::Paid;
            $this->paid_at = now();
            $this->payment_method = $method ?? $this->payment_method;
            $this->provider_payment_id = $providerPaymentId ?? $this->provider_payment_id;

            if ($amount !== null) {
                $this->amount = $amount;
            }

            if ($payload !== null) {
                $this->payload = $payload;
            }

            $this->save();

            $this->subscription?->markPaid(
                amount: (float) $this->amount,
                method: $method ?? 'gcash',
                reference: $this->reference_number,
            );
        });
    }

    public function markFailed(string $status = 'failed', ?array $payload = null): void
    {
        $this->status = PaymentState::from($status);
        $this->payload = $payload ?? $this->payload;
        $this->save();
    }
}
