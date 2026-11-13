<?php

namespace App\Filament\Resources\ClassBookingResource\Pages;

use App\Filament\Resources\ClassBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClassBooking extends EditRecord
{
    protected static string $resource = ClassBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
