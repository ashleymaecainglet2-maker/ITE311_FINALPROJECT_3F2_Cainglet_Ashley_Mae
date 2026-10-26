<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GymResource\Pages;
use App\Models\Gym;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GymResource extends Resource
{
    protected static ?string $model = Gym::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_platform_admin === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->is_platform_admin === true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Gym')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Used in the portal URL. Leave blank to generate from the name.'),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(30),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('address')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('city')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('province')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Owner')
                    ->schema([
                        Forms\Components\TextInput::make('owner_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('owner_email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('owner_phone')
                            ->tel()
                            ->maxLength(30),
                        Forms\Components\TextInput::make('owner_password')
                            ->label('Owner password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->helperText('The owner signs in with the email specified above.'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Subscription')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Gym account is active')
                            ->default(true),
                        Forms\Components\DatePicker::make('trial_ends_at')
                            ->native(false),
                        Forms\Components\TextInput::make('scanner_key')
                            ->label('Front-desk scanner key')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->helperText('The gym owner can retrieve this from the gym profile page.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('slug')
                    ->toggleable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('owner_name')
                    ->label('Owner')
                    ->searchable(),
                Tables\Columns\TextColumn::make('owner_email')
                    ->label('Owner email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('members_count')
                    ->label('Members')
                    ->counts('members'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->label('Trial ends')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGyms::route('/'),
            'create' => Pages\CreateGym::route('/create'),
            'edit' => Pages\EditGym::route('/{record}/edit'),
        ];
    }
}
