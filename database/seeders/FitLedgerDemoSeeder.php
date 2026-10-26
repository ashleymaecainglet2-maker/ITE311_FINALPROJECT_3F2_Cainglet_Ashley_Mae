<?php

namespace Database\Seeders;

use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FitLedgerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $gym = Gym::updateOrCreate(
            ['slug' => 'bakbakan-mma-fitness'],
            [
                'name' => 'Bakbakan MMA & Fitness',
                'address' => '12 Mabini Street',
                'city' => 'Quezon City',
                'province' => 'Metro Manila',
                'phone' => '0281234567',
                'email' => 'hello@bakbakan.test',
                'owner_name' => 'Migs Santos',
                'owner_email' => 'owner@fitledger.test',
                'owner_phone' => '09171234567',
                'is_active' => true,
                'trial_ends_at' => today()->addDays(30),
            ]
        );

        if (blank($gym->scanner_key)) {
            $gym->scanner_key = 'FLSCAN-'.Str::upper(Str::random(40));
            $gym->saveQuietly();
        }

        User::updateOrCreate(
            ['email' => 'owner@fitledger.test'],
            [
                'gym_id' => $gym->id,
                'name' => 'Migs Santos',
                'password' => 'password',
                'is_platform_admin' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@fitledger.test'],
            [
                'gym_id' => null,
                'name' => 'FitLedger Admin',
                'password' => 'password',
                'is_platform_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $daily = Plan::updateOrCreate(
            ['gym_id' => $gym->id, 'name' => 'Daily Walk-in'],
            [
                'description' => 'Single-day gym or mats access.',
                'price' => 150,
                'duration_days' => 1,
                'is_active' => true,
            ]
        );

        $monthly = Plan::updateOrCreate(
            ['gym_id' => $gym->id, 'name' => 'Monthly Contract'],
            [
                'description' => 'Unlimited training for 30 days.',
                'price' => 1500,
                'duration_days' => 30,
                'is_active' => true,
            ]
        );

        $student = Plan::updateOrCreate(
            ['gym_id' => $gym->id, 'name' => 'Student Monthly'],
            [
                'description' => 'Discounted 30-day plan with valid school ID.',
                'price' => 1200,
                'duration_days' => 30,
                'is_active' => true,
            ]
        );

        $members = [
            ['name' => 'Paolo Reyes', 'phone' => '09170000001', 'email' => 'paolo@example.test'],
            ['name' => 'Jasmine Cruz', 'phone' => '09170000002', 'email' => 'jasmine@example.test'],
            ['name' => 'Karlo Mendoza', 'phone' => '09170000003', 'email' => 'karlo@example.test'],
            ['name' => 'Bea Villanueva', 'phone' => '09170000004', 'email' => 'bea@example.test'],
            ['name' => 'Miguel Tan', 'phone' => '09170000005', 'email' => 'miguel@example.test'],
            ['name' => 'Andrea Lim', 'phone' => '09170000006', 'email' => 'andrea@example.test'],
            ['name' => 'Rafael Garcia', 'phone' => '09170000007', 'email' => 'rafael@example.test'],
            ['name' => 'Trisha Bautista', 'phone' => '09170000008', 'email' => 'trisha@example.test'],
        ];

        $created = collect($members)->map(function (array $data) use ($gym): Member {
            return Member::updateOrCreate(
                ['gym_id' => $gym->id, 'phone' => $data['phone']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'status' => MemberStatus::Active,
                    'joined_at' => today()->subDays(rand(30, 500)),
                    'emergency_contact_name' => 'Emergency Contact',
                    'emergency_contact_phone' => '09179999999',
                ]
            );
        });

        $this->subscription($created[0], $monthly, today()->subDays(10), today()->addDays(19), PaymentStatus::Paid);
        $this->subscription($created[1], $monthly, today()->subDays(28), today()->addDays(1), PaymentStatus::Unpaid);
        $this->subscription($created[2], $student, today()->subDays(40), today()->subDays(10), PaymentStatus::Unpaid);
        $this->subscription($created[3], $daily, today(), today(), PaymentStatus::Paid);
        $this->subscription($created[4], $monthly, today()->subDays(5), today()->addDays(24), PaymentStatus::Partial);
        $this->subscription($created[5], $student, today()->subDays(27), today()->addDays(2), PaymentStatus::Paid);

        if (! CheckIn::query()->where('gym_id', $gym->id)->exists()) {
            CheckIn::create([
                'gym_id' => $gym->id,
                'member_id' => $created[0]->id,
                'subscription_id' => $created[0]->latestSubscription?->id,
                'status' => 'active',
                'message' => 'Seeded demo check-in.',
                'scanned_at' => now(),
            ]);
        }
    }

    private function subscription(
        Member $member,
        Plan $plan,
        \DateTimeInterface $startsAt,
        \DateTimeInterface $endsAt,
        PaymentStatus $status,
    ): void {
        $paid = $status === PaymentStatus::Paid;

        Subscription::updateOrCreate(
            [
                'member_id' => $member->id,
                'plan_id' => $plan->id,
                'starts_at' => $startsAt,
            ],
            [
                'gym_id' => $member->gym_id,
                'ends_at' => $endsAt,
                'amount' => $plan->price,
                'amount_paid' => $paid ? $plan->price : ($status === PaymentStatus::Partial ? ((float) $plan->price / 2) : 0),
                'payment_status' => $status,
                'payment_method' => $paid ? 'gcash' : null,
                'reference' => $paid ? 'FL-'.Str::upper(Str::random(10)) : null,
                'paid_at' => $paid ? now()->subDays(2) : null,
            ]
        );
    }
}
