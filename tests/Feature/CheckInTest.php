<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->plan = Plan::factory()->for($this->gym)->create([
            'price' => 1500,
            'duration_days' => 30,
        ]);
    }

    public function test_active_paid_member_gets_a_green_response(): void
    {
        $member = $this->member();
        $subscription = $this->subscription($member, today()->subDays(5), today()->addDays(10), PaymentStatus::Paid);

        $response = $this->scan($member);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('color', 'green')
            ->assertJsonPath('allows_entry', true)
            ->assertJsonPath('member.name', $member->name)
            ->assertJsonPath('subscription.id', $subscription->id);

        $this->assertDatabaseHas('check_ins', [
            'gym_id' => $this->gym->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);
    }

    public function test_unpaid_active_member_is_yellow_due(): void
    {
        $member = $this->member();

        $this->subscription($member, today()->subDays(5), today()->addDays(10), PaymentStatus::Unpaid);

        $this->scan($member)
            ->assertOk()
            ->assertJsonPath('status', 'due')
            ->assertJsonPath('color', 'yellow')
            ->assertJsonPath('allows_entry', true)
            ->assertJsonPath('subscription.amount_due', 1500);
    }

    public function test_paid_but_expiring_member_is_yellow_due(): void
    {
        $member = $this->member();

        $this->subscription($member, today()->subDays(28), today()->addDays(2), PaymentStatus::Paid);

        $this->scan($member)
            ->assertOk()
            ->assertJsonPath('status', 'due')
            ->assertJsonPath('color', 'yellow');
    }

    public function test_expired_member_is_red(): void
    {
        $member = $this->member();

        $this->subscription($member, today()->subDays(40), today()->subDay(), PaymentStatus::Unpaid);

        $this->scan($member)
            ->assertOk()
            ->assertJsonPath('status', 'expired')
            ->assertJsonPath('color', 'red')
            ->assertJsonPath('allows_entry', false);
    }

    public function test_member_without_subscription_is_red(): void
    {
        $member = $this->member();

        $this->scan($member)
            ->assertOk()
            ->assertJsonPath('status', 'expired')
            ->assertJsonPath('allows_entry', false);
    }

    public function test_inactive_member_is_red(): void
    {
        $member = $this->member(['status' => MemberStatus::Suspended]);
        $this->subscription($member, today()->subDays(5), today()->addDays(10), PaymentStatus::Paid);

        $this->scan($member)
            ->assertOk()
            ->assertJsonPath('status', 'inactive')
            ->assertJsonPath('allows_entry', false);
    }

    public function test_unknown_qr_code_returns_not_found(): void
    {
        $this->scanRaw('FL-DOES-NOT-EXIST')
            ->assertNotFound()
            ->assertJsonPath('status', 'not_found');
    }

    public function test_member_from_another_gym_cannot_be_scanned(): void
    {
        $otherGym = Gym::factory()->create();
        $otherPlan = Plan::factory()->for($otherGym)->create();
        $otherMember = Member::factory()->for($otherGym)->create();
        $this->subscription(
            $otherMember,
            today()->subDays(2),
            today()->addDays(10),
            PaymentStatus::Paid,
            $otherPlan,
            $otherGym
        );

        $this->scan($otherMember)
            ->assertNotFound()
            ->assertJsonPath('status', 'not_found');
    }

    public function test_scanner_key_is_required(): void
    {
        $member = $this->member();

        $this->postJson('/api/check-in', ['qr_code' => $member->qr_code], [
            'X-Gym-Slug' => $this->gym->slug,
        ])->assertUnauthorized();
    }

    public function test_wrong_scanner_key_is_rejected(): void
    {
        $member = $this->member();

        $this->postJson('/api/check-in', ['qr_code' => $member->qr_code], [
            'X-Gym-Slug' => $this->gym->slug,
            'X-Scanner-Key' => 'wrong-key',
        ])->assertUnauthorized();
    }

    private function member(array $attributes = []): Member
    {
        return Member::factory()->for($this->gym)->create($attributes);
    }

    private function subscription(
        Member $member,
        \DateTimeInterface $startsAt,
        \DateTimeInterface $endsAt,
        PaymentStatus $status,
        ?Plan $plan = null,
        ?Gym $gym = null,
    ): Subscription {
        $plan ??= $this->plan;
        $gym ??= $this->gym;
        $paid = $status === PaymentStatus::Paid;

        return Subscription::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'amount' => $plan->price,
            'amount_paid' => $paid ? $plan->price : ($status === PaymentStatus::Partial ? ((float) $plan->price / 2) : 0),
            'payment_status' => $status,
            'payment_method' => $paid ? 'gcash' : null,
            'paid_at' => $paid ? now() : null,
        ]);
    }

    private function scan(Member $member): TestResponse
    {
        return $this->scanRaw($member->qr_code);
    }

    private function scanRaw(string $qrCode): TestResponse
    {
        return $this->postJson('/api/check-in', ['qr_code' => $qrCode], [
            'X-Gym-Slug' => $this->gym->slug,
            'X-Scanner-Key' => $this->gym->scanner_key,
        ]);
    }
}
