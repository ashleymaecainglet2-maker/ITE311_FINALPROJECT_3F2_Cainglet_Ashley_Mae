<?php

namespace App\Models\Concerns;

use App\Models\Gym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToGym
{
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function scopeForGym(Builder $query, Gym|int $gym): Builder
    {
        return $query->where(
            $this->getTable().'.gym_id',
            $gym instanceof Gym ? $gym->getKey() : $gym
        );
    }
}
