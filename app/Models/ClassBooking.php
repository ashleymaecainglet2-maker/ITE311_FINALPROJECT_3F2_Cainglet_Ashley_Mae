<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',         // Ito ang kulang kanina
        'gym_class_id',
        'member_id',
        'status',
    ];

    public function gymClass(): BelongsTo
    {
        return $this->belongsTo(GymClass::class, 'gym_class_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }
}