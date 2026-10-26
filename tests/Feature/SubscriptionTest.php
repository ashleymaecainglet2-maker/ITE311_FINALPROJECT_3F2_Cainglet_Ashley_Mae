<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_gets_a_unique_qr_code_on_creation(): void
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->for($gym)->create();

        $this->assertNotEmpty($member->qr_code);
        $this->assertStringStartsWith('FL-', $member->qr_code);
        $this->assertSame(MemberStatus::Active, $member->status);
    }

    public function test_qr_code_view_renders_an_svg(): void
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->for($gym)->create();

        $html = view('filament.members.qr-code', [
            'member' => $member,
            'size' => 120,
        ])->render();

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString($member->qr_code, $html);
    }

    public function test_subscription_defaults_amount_and_end_date_from_plan(): void
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
            'starts_at' => today(),
        ]);

        $this->assertSame('1500.00', $subscription->amount);
        $this->assertTrue($subscription->ends_at->isSameDay(today()->addDays(29)));
        $this->assertSame(PaymentStatus::Unpaid, $subscription->payment_status);
        $this->assertSame(1500.0, $subscription->amountDue());
    }

    public function test_payment_updates_balance_and_status(): void
    {
        [$subscription] = $this->subscriptionWithBalance(1500);

        $subscription->markPaid(750);

        $this->assertSame(PaymentStatus::Partial, $subscription->payment_status);
        $this->assertSame(750.0, $subscription->amountDue());

        $subscription->markPaid(750);

        $this->assertSame(PaymentStatus::Paid, $subscription->payment_status);
        $this->assertSame(0.0, $subscription->amountDue());
    }

    public function test_subscription_rejects_a_plan_from_another_gym(): void
    {
        $gym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();
        $member = Member::factory()->for($gym)->create();
        $foreignPlan = Plan::factory()->for($otherGym)->create();

        $this->expectException(ValidationException::class);

        Subscription::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'plan_id' => $foreignPlan->id,
            'starts_at' => today(),
        ]);
    }

    public function test_subscription_status_transitions(): void
    {
        [$active] = $this->subscriptionWithBalance(1500, PaymentStatus::Paid, today()->subDay(), today()->addDays(20));
        [$due] = $this->subscriptionWithBalance(1500, PaymentStatus::Unpaid, today()->subDay(), today()->addDays(20));
        [$expiring] = $this->subscriptionWithBalance(1500, PaymentStatus::Paid, today()->subDays(28), today()->addDay());
        [$expired] = $this->subscriptionWithBalance(1500, PaymentStatus::Paid, today()->subDays(30), today()->subDay());

        $this->assertSame('active', $active->accessStatus()->value);
        $this->assertSame('due', $due->accessStatus()->value);
        $this->assertSame('due', $expiring->accessStatus()->value);
        $this->assertSame('expired', $expired->accessStatus()->value);
    }

    /**
     * @return array{0: Subscription, 1: Member, 2: Plan, 3: Gym}
     */
    private function subscriptionWithBalance(
        float $amount,
        PaymentStatus $status = PaymentStatus::Unpaid,
        ?\DateTimeInterface $startsAt = null,
        ?\DateTimeInterface $endsAt = null,
    ): array {
        $gym = Gym::factory()->create();
        $plan = Plan::factory()->for($gym)->create([
            'price' => $amount,
            'duration_days' => 30,
        ]);
        $member = Member::factory()->for($gym)->create();

        $subscription = Subscription::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'starts_at' => $startsAt ?? today()->subDays(5),
            'ends_at' => $endsAt ?? today()->addDays(20),
            'amount' => $amount,
            'amount_paid' => $status === PaymentStatus::Paid ? $amount : 0,
            'payment_status' => $status,
            'paid_at' => $status === PaymentStatus::Paid ? now() : null,
        ]);

        return [$subscription, $member, $plan, $gym];
    }
}
