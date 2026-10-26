<?php

namespace App\Models;

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToGym;
use Carbon\CarbonInterface;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToGym, HasFactory;

    protected $fillable = [
        'gym_id',
        'member_id',
        'plan_id',
        'starts_at',
        'ends_at',
        'amount',
        'amount_paid',
        'payment_status',
        'payment_method',
        'reference',
        'paid_at',
        'last_reminded_at',
        'notes',
    ];

    protected $attributes = [
        'payment_status' => 'unpaid',
        'amount_paid' => 0,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'last_reminded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription): void {
            $subscription->starts_at ??= today();

            if ($subscription->plan_id) {
                $plan = Plan::find($subscription->plan_id);

                if ($plan) {
                    if ($subscription->amount === null) {
                        $subscription->amount = $plan->price;
                    }

                    if ($subscription->ends_at === null) {
                        $days = max(1, (int) $plan->duration_days);
                        $subscription->ends_at = Carbon::parse($subscription->starts_at)
                            ->addDays($days - 1);
                    }
                }
            }

            $subscription->ends_at ??= $subscription->starts_at;
        });

        static::saving(function (Subscription $subscription): void {
            $member = $subscription->member_id
                ? Member::find($subscription->member_id)
                : null;

            $plan = $subscription->plan_id
                ? Plan::find($subscription->plan_id)
                : null;

            if ($member && $plan && $member->gym_id !== $plan->gym_id) {
                throw ValidationException::withMessages([
                    'plan_id' => 'The selected plan belongs to a different gym.',
                ]);
            }

            if ($member) {
                $subscription->gym_id = $member->gym_id;
            }

            if ($member && $plan && $subscription->gym_id && $subscription->gym_id !== $member->gym_id) {
                throw ValidationException::withMessages([
                    'member_id' => 'The selected member belongs to a different gym.',
                ]);
            }

            if ($subscription->starts_at && $subscription->ends_at && $subscription->ends_at->lt($subscription->starts_at)) {
                throw ValidationException::withMessages([
                    'ends_at' => 'The end date must be on or after the start date.',
                ]);
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function accessStatus(): CheckInStatus
    {
        if ($this->payment_status === PaymentStatus::Refunded) {
            return CheckInStatus::Expired;
        }

        if ($this->starts_at->gt(today()) || $this->ends_at->lt(today())) {
            return CheckInStatus::Expired;
        }

        if ($this->payment_status !== PaymentStatus::Paid) {
            return CheckInStatus::Due;
        }

        $dueSoonDays = (int) config('fitledger.dues.due_soon_days', 3);

        if ($this->ends_at->lte(today()->addDays($dueSoonDays))) {
            return CheckInStatus::Due;
        }

        return CheckInStatus::Active;
    }

    public function amountDue(): float
    {
        return max(0, round((float) $this->amount - (float) $this->amount_paid, 2));
    }

    public function getAmountDueAttribute(): float
    {
        return $this->amountDue();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function isExpired(): bool
    {
        return $this->ends_at->lt(today());
    }

    public function markPaid(?float $amount = null, string $method = 'gcash', ?string $reference = null): void
    {
        $amount ??= $this->amountDue();
        $newTotal = round((float) $this->amount_paid + $amount, 2);

        $this->amount_paid = min((float) $this->amount, $newTotal);
        $this->payment_status = $this->amount_paid >= (float) $this->amount
            ? PaymentStatus::Paid
            : PaymentStatus::Partial;
        $this->paid_at = now();
        $this->payment_method = $method;
        $this->reference = $reference ?? $this->reference;

        $this->save();
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query
            ->whereDate('starts_at', '<=', today())
            ->whereDate('ends_at', '>=', today())
            ->where('payment_status', '!=', PaymentStatus::Refunded->value);
    }

    public function scopeDueWithin(Builder $query, int $days = 3): Builder
    {
        return $query->whereDate('ends_at', '<=', today()->addDays($days));
    }

    public function scopeNeedingReminder(Builder $query, int $days = 3): Builder
    {
        return $query
            ->whereDate('ends_at', '>=', today()->subDays(7))
            ->where(function (Builder $query) use ($days): void {
                $query
                    ->whereIn('payment_status', [
                        PaymentStatus::Unpaid->value,
                        PaymentStatus::Partial->value,
                    ])
                    ->orWhereDate('ends_at', '<=', today()->addDays($days));
            })
            ->where('payment_status', '!=', PaymentStatus::Refunded->value)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('last_reminded_at')
                    ->orWhere('last_reminded_at', '<=', now()->subDay());
            });
    }

    public function getDurationLabelAttribute(): string
    {
        return $this->starts_at->format('M j').' - '.$this->ends_at->format('M j, Y');
    }

    public function endsOn(CarbonInterface $date): bool
    {
        return $this->ends_at->isSameDay($date);
    }
}
