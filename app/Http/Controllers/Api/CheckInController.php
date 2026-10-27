<?php

namespace App\Http\Controllers\Api;

use App\Enums\CheckInStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr_code' => ['nullable', 'string', 'max:100', 'required_without:code'],
            'code' => ['nullable', 'string', 'max:100', 'required_without:qr_code'],
        ]);

        $qrCode = trim((string) ($data['qr_code'] ?? $data['code']));
        $gym = $request->attributes->get('scanner_gym');

        $member = Member::query()
            ->with(['gym', 'latestSubscription.plan'])
            ->where('gym_id', $gym?->getKey())
            ->where('qr_code', $qrCode)
            ->first();

        if (! $member) {
            return response()->json([
                'status' => 'not_found',
                'color' => 'red',
                'color_hex' => '#dc2626',
                'allows_entry' => false,
                'message' => 'Member not found. Please check the QR code.',
            ], 404);
        }

        if (! $member->gym?->is_active) {
            return response()->json([
                'status' => 'inactive',
                'color' => 'red',
                'color_hex' => '#dc2626',
                'allows_entry' => false,
                'message' => 'This gym account is inactive.',
                'member' => $this->memberPayload($member),
            ], 403);
        }

        if ($member->status !== MemberStatus::Active) {
            $status = CheckInStatus::Inactive;
            $subscription = null;
            $message = 'Member is '.strtolower($member->status->getLabel()).'. Please see the front desk.';
        } else {
            $subscription = $member->subscriptions()
                ->whereDate('starts_at', '<=', today())
                ->whereDate('ends_at', '>=', today())
                ->where('payment_status', '!=', PaymentStatus::Refunded->value)
                ->latest('ends_at')
                ->first();

            if (! $subscription) {
                $status = CheckInStatus::Expired;
                $latest = $member->latestSubscription;

                $message = $latest
                    ? "Subscription expired on {$latest->ends_at->format('M j, Y')}."
                    : 'No subscription on file. Please enroll at the front desk.';
            } else {
                $status = $subscription->accessStatus();

                $message = match ($status) {
                    CheckInStatus::Active => "Welcome, {$member->name}! Valid until {$subscription->ends_at->format('M j, Y')}.",
                    CheckInStatus::Due => $subscription->payment_status !== PaymentStatus::Paid
                        ? 'Payment due: PHP '.number_format($subscription->amountDue(), 2).' outstanding.'
                        : "Expiring soon: renew before {$subscription->ends_at->format('M j, Y')}.",
                    default => 'Subscription expired.',
                };
            }
        }

        $checkIn = CheckIn::create([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'subscription_id' => $subscription?->id,
            'status' => $status,
            'message' => $message,
            'ip_address' => $request->ip(),
            'scanned_at' => now(),
        ]);

        return response()->json([
            'status' => $status->value,
            'color' => $status->colorName(),
            'color_hex' => $status->colorHex(),
            'allows_entry' => $status->allowsEntry(),
            'message' => $message,
            'member' => $this->memberPayload($member),
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'plan' => $subscription->plan?->name,
                'starts_at' => $subscription->starts_at->toDateString(),
                'ends_at' => $subscription->ends_at->toDateString(),
                'payment_status' => $subscription->payment_status->value,
                'amount' => (float) $subscription->amount,
                'amount_due' => $subscription->amountDue(),
            ] : null,
            'checked_in_at' => $checkIn->scanned_at->toIso8601String(),
            'scanner' => [
                'gym' => $gym?->name,
                'scanner_device_ip' => $request->ip(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPayload(Member $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'phone' => $member->phone,
            'status' => $member->status->value,
            'gym' => $member->gym?->name,
            'gym_slug' => $member->gym?->slug,
        ];
    }
}
