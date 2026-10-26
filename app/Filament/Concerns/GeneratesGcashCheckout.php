<?php

namespace App\Filament\Concerns;

use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\Subscription;
use App\Services\PayMongoService;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

trait GeneratesGcashCheckout
{
    protected static function gcashCheckoutAction(): Action
    {
        return Action::make('gcashCheckout')
            ->label('GCash link')
            ->icon('heroicon-o-device-phone-mobile')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Generate GCash checkout')
            ->modalDescription(function (Subscription|Member $record): string {
                $subscription = $record instanceof Member
                    ? $record->outstandingSubscription()
                    : $record;

                if (! $subscription) {
                    return 'No outstanding subscription was found.';
                }

                return 'Create a GCash checkout link for PHP '
                    .number_format($subscription->amountDue(), 2)
                    .'. The link can be sent to the member by SMS or messenger.';
            })
            ->modalSubmitActionLabel('Generate link')
            ->visible(function (Subscription|Member $record): bool {
                $subscription = $record instanceof Member
                    ? $record->outstandingSubscription()
                    : $record;

                return $subscription !== null
                    && $subscription->amountDue() > 0
                    && $subscription->payment_status !== PaymentStatus::Refunded;
            })
            ->action(function (Subscription|Member $record): void {
                $subscription = $record instanceof Member
                    ? $record->outstandingSubscription()
                    : $record;

                if (! $subscription) {
                    Notification::make()
                        ->warning()
                        ->title('No outstanding subscription')
                        ->send();

                    return;
                }

                try {
                    $payment = app(PayMongoService::class)->createGcashCheckout($subscription);
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Unable to create GCash link')
                        ->body($exception->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('GCash checkout ready')
                    ->body('Send this link to '.$subscription->member->name.': '.$payment->checkout_url)
                    ->persistent()
                    ->actions([
                        NotificationAction::make('open')
                            ->label('Open checkout')
                            ->url($payment->checkout_url)
                            ->openUrlInNewTab()
                            ->button(),
                    ])
                    ->send();
            });
    }
}
