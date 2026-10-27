<?php

namespace App\Models;

use Database\Factories\GymFactory;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Gym extends Model implements HasName
{
    /** @use HasFactory<GymFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'city',
        'province',
        'phone',
        'email',
        'owner_name',
        'owner_email',
        'owner_phone',
        'is_active',
        'trial_ends_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'trial_ends_at' => 'date',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Gym $gym): void {
            if (blank($gym->slug)) {
                $gym->slug = static::uniqueSlug($gym->name);
            }

            if (blank($gym->scanner_key)) {
                $gym->scanner_key = 'FLSCAN-'.Str::upper(Str::random(40));
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'gym';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Retrieve the operating schedule for the gym.
     */
    public function getOperatingSchedule(): array
    {
        return $this->settings['schedule'] ?? [
            'opening_time' => config('fitledger.schedules.default_opening_time', '06:00'),
            'closing_time' => config('fitledger.schedules.default_closing_time', '22:00'),
            'operating_days' => config('fitledger.schedules.operating_days', []),
        ];
    }

    /**
     * Check if the gym is currently open based on operating schedule.
     */
    public function isOpenNow(): bool
    {
        $schedule = $this->getOperatingSchedule();
        $currentDay = now()->format('l');

        if (! in_array($currentDay, $schedule['operating_days'] ?? [])) {
            return false;
        }

        $currentTime = now()->format('H:i');
        return $currentTime >= $schedule['opening_time'] && $currentTime <= $schedule['closing_time'];
    }
}
