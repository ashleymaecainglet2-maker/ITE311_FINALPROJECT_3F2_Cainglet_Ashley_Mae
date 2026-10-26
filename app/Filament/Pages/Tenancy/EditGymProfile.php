<?php

namespace App\Filament\Pages\Tenancy;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile;

class EditGymProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Gym profile';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(30),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('address')
                    ->maxLength(255),
                TextInput::make('city')
                    ->maxLength(255),
                TextInput::make('province')
                    ->maxLength(255),
                TextInput::make('owner_name')
                    ->label('Owner name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('owner_email')
                    ->label('Owner email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('owner_phone')
                    ->label('Owner phone')
                    ->tel()
                    ->maxLength(30),
                TextInput::make('scanner_key')
                    ->label('Front-desk scanner key')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull()
                    ->helperText('Send this key with the scanner request as X-Scanner-Key. Never share it publicly.'),
            ])
            ->columns(2);
    }
}
