<div class="flex flex-col items-center justify-center gap-3 p-4">
    <div class="rounded-xl bg-white p-3 ring-1 ring-gray-950/10 dark:ring-white/10">
        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
            ->size($size ?? 220)
            ->margin(1)
            ->errorCorrection('H')
            ->backgroundColor(255, 255, 255)
            ->generate($member->qr_code) !!}
    </div>

    <div class="text-center">
        <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $member->name }}</div>
        <div class="mt-1 font-mono text-xs text-gray-500">{{ $member->qr_code }}</div>
    </div>
</div>
