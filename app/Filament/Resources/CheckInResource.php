<?php

namespace App\Filament\Resources;

use App\Enums\CheckInStatus;
use App\Filament\Resources\CheckInResource\Pages;
use App\Models\CheckIn;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CheckInResource extends Resource
{
    protected static ?string $model = CheckIn::class;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Front desk';

    protected static ?string $modelLabel = 'check-in';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['member', 'subscription.plan']))
            ->columns([
                Tables\Columns\TextColumn::make('scanned_at')
                    ->label('Scanned')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('member.name')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('subscription.plan.name')
                    ->label('Plan')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('message')
                    ->limit(55)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(CheckInStatus::class),
                Tables\Filters\Filter::make('today')
                    ->label('Today only')
                    ->query(fn (Builder $query) => $query->whereDate('scanned_at', today())),
            ])
            ->defaultSort('scanned_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCheckIns::route('/'),
        ];
    }
}
