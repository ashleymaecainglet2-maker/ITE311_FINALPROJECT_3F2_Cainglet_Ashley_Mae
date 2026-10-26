<?php

namespace App\Models;

use App\Enums\CheckInStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToGym;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use BelongsToGym, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'gym_id',
        'name',
        'phone',
        'email',
        'qr_code',
        'status',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'joined_at',
        'notes',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'joined_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Member $member): void {
            if (blank($member->qr_code)) {
                $member->qr_code = 'FL-'.Str::ulid();
            }
        });
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany('ends_at');
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereDate('starts_at', '<=', today())
            ->whereDate('ends_at', '>=', today())
            ->where('payment_status', '!=', PaymentStatus::Refunded->value)
            ->latestOfMany('ends_at');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function accessStatus(): CheckInStatus
    {
        if ($this->status !== MemberStatus::Active) {
            return CheckInStatus::Inactive;
        }

        $subscription = $this->activeSubscription ?? $this->latestSubscription;

        if (! $subscription) {
            return CheckInStatus::Expired;
        }

        return $subscription->accessStatus();
    }

    /**
     * The most relevant subscription that still has an outstanding balance.
     */
    public function outstandingSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereNotIn('payment_status', [
                PaymentStatus::Paid->value,
                PaymentStatus::Refunded->value,
            ])
            ->whereDate('ends_at', '>=', today()->subDays(7))
            ->latest('ends_at')
            ->first();
    }

    public function scopeActive($query)
    {
        return $query->where('status', MemberStatus::Active->value);
    }
}
