<?php

namespace App\Filament\Resources;

use App\Enums\CheckInStatus;
use App\Enums\PaymentStatus;
use App\Filament\Concerns\GeneratesGcashCheckout;
use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Plan;
use App\Models\Subscription;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SubscriptionResource extends Resource
{
    use GeneratesGcashCheckout;

    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Membership')
                    ->schema([
                        Forms\Components\Select::make('member_id')
                            ->relationship(
                                'member',
                                'name',
                                modifyQueryUsing: fn (Builder $query) => $query
                                    ->where('gym_id', Filament::getTenant()?->getKey())
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('plan_id')
                            ->relationship(
                                'plan',
                                'name',
                                modifyQueryUsing: fn (Builder $query) => $query
                                    ->where('gym_id', Filament::getTenant()?->getKey())
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                $plan = static::findPlan($state);

                                if (! $plan) {
                                    return;
                                }

                                $set('amount', $plan->price);

                                if (blank($get('starts_at'))) {
                                    $set('starts_at', today()->toDateString());
                                }

                                $set('ends_at', static::calculateEndDate($get('starts_at'), $plan));
                            }),
                        Forms\Components\DatePicker::make('starts_at')
                            ->required()
                            ->default(fn () => today())
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                $plan = static::findPlan($get('plan_id'));

                                if (! $plan) {
                                    return;
                                }

                                $set('ends_at', static::calculateEndDate($state, $plan));
                            }),
                        Forms\Components\DatePicker::make('ends_at')
                            ->required()
                            ->native(false)
                            ->afterOrEqual('starts_at'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Payment')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('PHP')
                            ->required()
                            ->minValue(0)
                            ->step(0.01),
                        Forms\Components\TextInput::make('amount_paid')
                            ->label('Amount paid')
                            ->numeric()
                            ->prefix('PHP')
                            ->default(0)
                            ->minValue(0)
                            ->step(0.01),
                        Forms\Components\Select::make('payment_status')
                            ->options(PaymentStatus::class)
                            ->required()
                            ->default(PaymentStatus::Unpaid)
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if ($state === PaymentStatus::Paid->value) {
                                    $set('amount_paid', $get('amount'));
                                    $set('paid_at', now());
                                }
                            }),
                        Forms\Components\TextInput::make('payment_method')
                            ->label('Payment method')
                            ->placeholder('gcash, cash, bank transfer'),
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference number')
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('paid_at')
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['member', 'plan']))
            ->columns([
                Tables\Columns\TextColumn::make('member.name')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->date('M j, Y')
                    ->sortable()
                    ->color(fn (Subscription $record): string => match (true) {
                        $record->isExpired() => 'danger',
                        $record->ends_at->lte(today()->addDays((int) config('fitledger.dues.due_soon_days', 3))) => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->money('PHP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->money('PHP')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge(),
                Tables\Columns\TextColumn::make('access_status')
                    ->label('Entry')
                    ->badge()
                    ->state(fn (Subscription $record): CheckInStatus => $record->accessStatus())
                    ->color(fn ($state) => $state instanceof CheckInStatus ? $state->getColor() : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options(PaymentStatus::class),
                Tables\Filters\Filter::make('expiring')
                    ->label('Expiring within 7 days')
                    ->query(fn (Builder $query) => $query
                        ->whereDate('ends_at', '>=', today())
                        ->whereDate('ends_at', '<=', today()->addDays(7))),
                Tables\Filters\Filter::make('unpaid')
                    ->label('Outstanding balance')
                    ->query(fn (Builder $query) => $query->whereIn('payment_status', [
                        PaymentStatus::Unpaid->value,
                        PaymentStatus::Partial->value,
                    ])),
            ])
            ->actions([
                static::gcashCheckoutAction(),
                Tables\Actions\Action::make('markPaid')
                    ->label('Mark paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Subscription $record): bool => $record->payment_status !== PaymentStatus::Paid)
                    ->action(function (Subscription $record): void {
                        $record->markPaid();

                        Notification::make()
                            ->success()
                            ->title('Subscription marked as paid')
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('ends_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }

    private static function findPlan(mixed $planId): ?Plan
    {
        if (blank($planId)) {
            return null;
        }

        return Plan::query()
            ->where('gym_id', Filament::getTenant()?->getKey())
            ->find($planId);
    }

    private static function calculateEndDate(mixed $startsAt, Plan $plan): string
    {
        $start = $startsAt ? Carbon::parse($startsAt) : today();

        return $start
            ->addDays(max(1, (int) $plan->duration_days) - 1)
            ->toDateString();
    }
}
