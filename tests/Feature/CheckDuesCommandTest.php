<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\MemberDueReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckDuesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_reminder_and_marks_the_subscription(): void
    {
        Notification::fake();

        [$subscription, $member] = $this->dueSubscription();

        $this->artisan('fitledger:check-dues')
            ->expectsOutputToContain('FitLedger due check complete')
            ->assertSuccessful();

        Notification::assertSentOnDemand(
            MemberDueReminder::class,
            fn (MemberDueReminder $notification, array $channels, $notifiable): bool => $notification->subscription->is($subscription)
                && $notifiable->routes['mail'] === $member->email
        );

        $this->assertNotNull($subscription->fresh()->last_reminded_at);
    }

    public function test_it_does_not_send_duplicate_reminders_on_the_same_day(): void
    {
        Notification::fake();

        $this->dueSubscription();

        $this->artisan('fitledger:check-dues')->assertSuccessful();
        $this->artisan('fitledger:check-dues')->assertSuccessful();

        Notification::assertSentOnDemandTimes(MemberDueReminder::class, 1);
    }

    public function test_dry_run_sends_nothing(): void
    {
        Notification::fake();

        $this->dueSubscription();

        $this->artisan('fitledger:check-dues', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_it_skips_members_without_an_email_unless_sms_is_enabled(): void
    {
        Notification::fake();

        [, $member] = $this->dueSubscription();
        $member->update(['email' => null]);

        $this->artisan('fitledger:check-dues')->assertSuccessful();

        Notification::assertNothingSent();
    }

    /**
     * @return array{0: Subscription, 1: Member}
     */
    private function dueSubscription(): array
    {
        $gym = Gym::factory()->create();
        $plan = Plan::factory()->for($gym)->create([
            'price' => 1500,
            'duration_days' => 30,
        ]);
        $member = Member::factory()->for($gym)->create();
        $subscription = Subscription::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'starts_at' => today()->subDays(28),
            'ends_at' => today()->addDays(2),
            'amount' => 1500,
            'amount_paid' => 0,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        return [$subscription, $member];
    }
}
