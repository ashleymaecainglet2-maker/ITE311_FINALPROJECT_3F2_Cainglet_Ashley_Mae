<?php

namespace App\Filament\Widgets;

use App\Models\CheckIn;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestCheckIns extends BaseWidget
{
    protected static ?string $heading = 'Recent check-ins';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        return $table
            ->query(
                CheckIn::query()
                    ->with('member')
                    ->when($tenant, fn ($query) => $query->where('gym_id', $tenant->getKey()))
                    ->latest('scanned_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('member.name')
                    ->label('Member')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('message')
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('scanned_at')
                    ->label('Scanned')
                    ->since()
                    ->dateTimeTooltip('M j, Y g:i A'),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No check-ins yet')
            ->emptyStateDescription('Scans from the front desk will appear here.');
    }
}
