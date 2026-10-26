<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberDueReminder extends Notification
{
    use Queueable;

    public function __construct(public readonly Subscription $subscription) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return filled($this->subscription->member?->email) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->subscription;
        $member = $subscription->member;
        $gym = $subscription->gym;
        $plan = $subscription->plan;

        $expiryLine = $subscription->isExpired()
            ? "expired on {$subscription->ends_at->format('F j, Y')}"
            : "expires on {$subscription->ends_at->format('F j, Y')}";

        $mail = (new MailMessage)
            ->subject("Payment reminder - {$gym->name}")
            ->greeting("Hi {$member->name},")
            ->line("Your {$plan->name} subscription at {$gym->name} {$expiryLine}.");

        if ($subscription->amountDue() > 0) {
            $mail->line('Outstanding balance: PHP '.number_format($subscription->amountDue(), 2));
            $mail->line('Please settle at the front desk or ask us for a GCash checkout link.');
        } else {
            $mail->line('Your subscription is paid. Visit the front desk to renew and keep training.');
        }

        return $mail
            ->line('If you have already paid, kindly ignore this message.')
            ->salutation('FitLedger');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'member_id' => $this->subscription->member_id,
            'gym_id' => $this->subscription->gym_id,
            'ends_at' => $this->subscription->ends_at->toDateString(),
            'amount_due' => $this->subscription->amountDue(),
        ];
    }
}
