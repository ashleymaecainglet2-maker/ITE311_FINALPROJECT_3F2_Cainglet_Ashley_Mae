<?php

namespace App\Filament\Resources;

use App\Enums\CheckInStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Filament\Concerns\GeneratesGcashCheckout;
use App\Filament\Resources\MemberResource\Pages;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class MemberResource extends Resource
{
    use GeneratesGcashCheckout;

    protected static ?string $model = Member::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Members';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Member details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->maxLength(30),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->options(MemberStatus::class)
                            ->required()
                            ->default(MemberStatus::Active),
                        Forms\Components\DatePicker::make('joined_at')
                            ->default(today())
                            ->native(false),
                        Forms\Components\TextInput::make('address')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Emergency contact')
                    ->schema([
                        Forms\Components\TextInput::make('emergency_contact_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('emergency_contact_phone')
                            ->tel()
                            ->maxLength(30),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Forms\Components\Section::make('QR access')
                    ->schema([
                        Forms\Components\Placeholder::make('qr_preview')
                            ->label('Member QR code')
                            ->content(function (?Member $record): HtmlString|string {
                                if (! $record) {
                                    return 'A unique QR code is generated automatically when the member is saved.';
                                }

                                return new HtmlString(
                                    view('filament.members.qr-code', [
                                        'member' => $record,
                                        'size' => 180,
                                    ])->render()
                                );
                            }),
                    ])
                    ->visible(fn (string $operation): bool => $operation === 'edit'),

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'activeSubscription.plan',
                'latestSubscription.plan',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('latestSubscription.plan.name')
                    ->label('Plan')
                    ->placeholder('No plan')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('latestSubscription.ends_at')
                    ->label('Expires')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('entry_status')
                    ->label('Entry status')
                    ->badge()
                    ->state(fn (Member $record): CheckInStatus => $record->accessStatus())
                    ->color(fn ($state) => $state instanceof CheckInStatus ? $state->getColor() : null),
                Tables\Columns\TextColumn::make('joined_at')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(MemberStatus::class),
                Tables\Filters\Filter::make('outstanding')
                    ->label('Has outstanding balance')
                    ->query(fn (Builder $query) => $query->whereHas(
                        'subscriptions',
                        fn (Builder $subscriptionQuery) => $subscriptionQuery->whereIn('payment_status', [
                            PaymentStatus::Unpaid->value,
                            PaymentStatus::Partial->value,
                        ])
                    )),
                Tables\Filters\Filter::make('expiring')
                    ->label('Expiring within 7 days')
                    ->query(fn (Builder $query) => $query->whereHas(
                        'subscriptions',
                        fn (Builder $subscriptionQuery) => $subscriptionQuery
                            ->whereDate('starts_at', '<=', today())
                            ->whereDate('ends_at', '>=', today())
                            ->whereDate('ends_at', '<=', today()->addDays(7))
                    )),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('qrCode')
                    ->label('QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->modalHeading(fn (Member $record): string => "QR code - {$record->name}")
                    ->modalContent(fn (Member $record) => view('filament.members.qr-code', [
                        'member' => $record,
                        'size' => 260,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                static::gcashCheckoutAction(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'email', 'qr_code'];
    }
}
