<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GymClass extends Model
{
    use HasFactory;

    // Ito yung mga pwedeng i-save sa database
    protected $fillable = [
        'gym_id',
        'name',
        'capacity',
        'schedule_time',
        'duration_minutes',
        'instructor',
    ];

    // Koneksyon: Ang class na ito ay pag-aari ng isang Gym
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    // Koneksyon: Ang isang class ay pwedeng magkaroon ng maraming bookings
    public function bookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class, 'gym_class_id');
    }
}