<?php

namespace App\Filament\Resources\ClassBookingResource\Pages;

use App\Filament\Resources\ClassBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClassBookings extends ListRecords
{
    protected static string $resource = ClassBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
