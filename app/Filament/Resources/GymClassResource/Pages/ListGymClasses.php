<?php

namespace App\Filament\Resources\GymClassResource\Pages;

use App\Filament\Resources\GymClassResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGymClasses extends ListRecords
{
    protected static string $resource = GymClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
