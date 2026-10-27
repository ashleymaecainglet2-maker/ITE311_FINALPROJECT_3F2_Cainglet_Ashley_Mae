<?php

namespace App\Models;

use App\Enums\CheckInStatus;
use App\Models\Concerns\BelongsToGym;
use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    /** @use HasFactory<CheckInFactory> */
    use BelongsToGym, HasFactory;

    protected $fillable = [
        'gym_id',
        'member_id',
        'subscription_id',
        'status',
        'message',
        'ip_address',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CheckInStatus::class,
            'scanned_at' => 'datetime',
        ];
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
     * Scope check-ins to recent scans for a given member to prevent duplicate rapid scans.
     */
    public function scopeRecentForMember($query, int $memberId, int $seconds = 30)
    {
        return $query->where('member_id', $memberId)
            ->where('scanned_at', '>=', now()->subSeconds($seconds));
    }
}
