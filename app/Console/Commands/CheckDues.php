<?php

namespace App\Console\Commands;

use App\Enums\MemberStatus;
use App\Models\Subscription;
use App\Notifications\MemberDueReminder;
use App\Services\SemaphoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CheckDues extends Command
{
    protected $signature = 'fitledger:check-dues
                            {--days= : Days before expiry to start warning (defaults to config)}
                            {--gym= : Limit to a single gym slug}
                            {--sms : Also attempt SMS reminders via Semaphore}
                            {--dry-run : Show what would be sent without sending}';

    protected $description = 'Find expiring or unpaid subscriptions and send renewal reminders';

    public function handle(SemaphoreService $sms): int
    {
        $days = (int) ($this->option('days') ?? config('fitledger.dues.due_soon_days', 3));
        $gymSlug = $this->option('gym');
        $dryRun = (bool) $this->option('dry-run');
        $sendSms = (bool) $this->option('sms') || (bool) config('services.semaphore.enabled', false);

        $subscriptions = Subscription::query()
            ->with(['gym', 'member', 'plan'])
            ->needingReminder($days)
            ->whereHas('gym', fn ($query) => $query->where('is_active', true))
            ->whereHas('member', fn ($query) => $query->where('status', MemberStatus::Active->value))
            ->when($gymSlug, fn ($query) => $query->whereHas(
                'gym',
                fn ($gymQuery) => $gymQuery->where('slug', $gymSlug)
            ))
            ->orderBy('ends_at')
            ->lazyById(100);

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            $member = $subscription->member;
            $gym = $subscription->gym;
            $plan = $subscription->plan;

            if (! $member || ! $gym || ! $plan) {
                $skipped++;

                continue;
            }

            $message = $this->smsMessage($subscription);

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] %s | %s | %s | PHP %s | %s',
                    $gym->name,
                    $member->name,
                    $plan->name,
                    number_format($subscription->amountDue(), 2),
                    $subscription->ends_at->toDateString()
                ));
                $sent++;

                continue;
            }

            $delivered = false;

            if (filled($member->email)) {
                try {
                    Notification::route('mail', $member->email)
                        ->notify(new MemberDueReminder($subscription));

                    $delivered = true;
                } catch (\Throwable $exception) {
                    Log::error('FitLedger email reminder failed.', [
                        'subscription_id' => $subscription->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            if ($sendSms && filled($member->phone)) {
                try {
                    $delivered = $sms->send($member->phone, $message) || $delivered;
                } catch (\Throwable $exception) {
                    Log::error('FitLedger SMS reminder failed.', [
                        'subscription_id' => $subscription->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            if ($delivered) {
                $subscription->forceFill(['last_reminded_at' => now()])->save();
                $sent++;
                $this->line("Reminded {$member->name} ({$gym->name}) - due {$subscription->ends_at->toDateString()}");
            } else {
                $failed++;
                $this->warn("No reminder sent for {$member->name} ({$gym->name}) - no usable channel.");
            }
        }

        $this->newLine();
        $this->info("FitLedger due check complete: {$sent} sent, {$failed} failed, {$skipped} skipped.");

        return self::SUCCESS;
    }

    private function smsMessage(Subscription $subscription): string
    {
        $member = $subscription->member;
        $gym = $subscription->gym;
        $expiry = $subscription->ends_at->format('M j, Y');
        $state = $subscription->isExpired() ? "expired {$expiry}" : "expires {$expiry}";

        if ($subscription->amountDue() <= 0) {
            return sprintf(
                'FitLedger: Hi %s, your %s at %s %s. Your subscription is paid. Visit the front desk to renew.',
                $member->name,
                $subscription->plan->name,
                $gym->name,
                $state
            );
        }

        return sprintf(
            'FitLedger: Hi %s, your %s at %s %s. Balance PHP %s. Please settle at the front desk or request a GCash link.',
            $member->name,
            $subscription->plan->name,
            $gym->name,
            $state,
            number_format($subscription->amountDue(), 2)
        );
    }
}
