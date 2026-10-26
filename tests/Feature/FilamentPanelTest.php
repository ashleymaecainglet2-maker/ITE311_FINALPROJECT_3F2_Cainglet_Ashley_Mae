<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentPanelTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->create([
            'gym_id' => $this->gym->id,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $plan = Plan::factory()->for($this->gym)->create();
        $member = Member::factory()->for($this->gym)->create();

        Subscription::create([
            'gym_id' => $this->gym->id,
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'starts_at' => today()->subDays(2),
            'ends_at' => today()->addDays(27),
            'amount' => $plan->price,
            'amount_paid' => $plan->price,
            'payment_status' => PaymentStatus::Paid,
        ]);
    }

    public function test_owner_can_open_every_tenant_resource(): void
    {
        $base = "/admin/{$this->gym->slug}";

        $this->actingAs($this->owner);

        $this->get($base)->assertSuccessful();
        $this->get("{$base}/members")->assertSuccessful();
        $this->get("{$base}/subscriptions")->assertSuccessful();
        $this->get("{$base}/plans")->assertSuccessful();
        $this->get("{$base}/payments")->assertSuccessful();
        $this->get("{$base}/check-ins")->assertSuccessful();
        $this->get("{$base}/users")->assertSuccessful();
        $this->get("{$base}/profile")->assertSuccessful();
    }

    public function test_owner_cannot_access_another_gym(): void
    {
        $otherGym = Gym::factory()->create();

        $this->actingAs($this->owner)
            ->get("/admin/{$otherGym->slug}/members")
            ->assertNotFound();
    }

    public function test_owner_cannot_manage_platform_gyms(): void
    {
        $this->actingAs($this->owner)
            ->get("/admin/{$this->gym->slug}/gyms")
            ->assertForbidden();
    }

    public function test_platform_admin_can_access_the_gym_management_resource(): void
    {
        $admin = User::factory()->create([
            'gym_id' => null,
            'is_platform_admin' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get("/admin/{$this->gym->slug}/gyms")
            ->assertSuccessful();
    }
}
