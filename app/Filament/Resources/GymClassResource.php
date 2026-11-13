<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GymClassResource\Pages;
use App\Models\GymClass;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GymClassResource extends Resource
{
    protected static ?string $model = GymClass::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // Para ma-link sa Gym tenant
    protected static ?string $tenantOwnershipRelationshipName = 'gym';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Class Name')
                    ->required(),
                Forms\Components\TextInput::make('capacity')
                    ->label('Capacity')
                    ->numeric()
                    ->required(),
                Forms\Components\DateTimePicker::make('schedule_time')
                    ->label('Schedule Time')
                    ->required(),
                Forms\Components\TextInput::make('duration_minutes')
                    ->label('Duration (Minutes)')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('instructor')
                    ->label('Instructor')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Class Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('schedule_time')
                    ->label('Schedule')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('instructor')
                    ->label('Instructor')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGymClasses::route('/'),
            'create' => Pages\CreateGymClass::route('/create'),
            'edit' => Pages\EditGymClass::route('/{record}/edit'),
        ];
    }
}