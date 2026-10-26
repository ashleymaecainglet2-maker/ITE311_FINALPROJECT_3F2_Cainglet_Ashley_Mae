<?php

namespace App\Filament\Resources\GymResource\Pages;

use App\Filament\Resources\GymResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateGym extends CreateRecord
{
    protected static string $resource = GymResource::class;

    protected function afterCreate(): void
    {
        $gym = $this->record;
        $password = $this->data['owner_password'] ?? Str::random(20);

        User::firstOrCreate(
            ['email' => $gym->owner_email],
            [
                'gym_id' => $gym->id,
                'name' => $gym->owner_name,
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
