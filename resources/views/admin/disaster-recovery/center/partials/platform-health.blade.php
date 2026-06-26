@php
$healthColor = fn(string $h) => match($h) {
    'OK'           => 'text-green-700 bg-green-100 border-green-200',
    'STALE'        => 'text-amber-700 bg-amber-100 border-amber-200',
    'FAILED'       => 'text-red-700 bg-red-100 border-red-200',
    'ARVAN_MISSING'=> 'text-orange-700 bg-orange-100 border-orange-200',
    default        => 'text-gray-700 bg-gray-100 border-gray-200',
};
$healthLabel = fn(string $h) => match($h) {
    'OK'           => 'سالم',
    'STALE'        => 'قدیمی',
    'FAILED'       => 'ناموفق',
    'ARVAN_MISSING'=> 'آروان ناموجود',
    'MISSING'      => 'غایب',
    default        => $h,
};

// Compute Jalali backup ages
$dbAge      = $lastDb?->completed_at      ? \App\Helpers\Jalali::diffForHumansFa($lastDb->completed_at)      : null;
$storageAge = $lastStorage?->completed_at ? \App\Helpers\Jalali::diffForHumansFa($lastStorage->completed_at) : null;
@endphp

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
        </svg>
        <h2 class="text-sm font-semibold text-gray-700">سلامت بکاپ پلتفرم</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x md:divide-x-reverse divide-gray-100">

        {{-- DB Backup --}}
        <div class="p-5 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">بکاپ دیتابیس</span>
                <span class="px-2.5 py-1 text-xs font-semibold rounded-full border {{ $healthColor($dbHealth) }}">
                    {{ $healthLabel($dbHealth) }}
                </span>
            </div>
            @if($lastDb)
            <dl class="space-y-1.5 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">آخرین بکاپ</dt>
                    <dd class="text-gray-800 font-medium">
                        {{ $dbAge ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">سن بکاپ</dt>
                    <dd class="font-medium
                        {{ $lastDb->completed_at?->lt(now()->subHours(24)) ? 'text-amber-600' : 'text-green-600' }}">
                        @if($lastDb->completed_at)
                        {{ \App\Helpers\Jalali::formatFa($lastDb->completed_at) }}
                        @else
                        —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">وضعیت</dt>
                    <dd>
                        <span class="px-2 py-0.5 rounded text-xs font-medium
                            {{ $lastDb->isSuccess() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $lastDb->isSuccess() ? 'موفق' : 'ناموفق' }}
                        </span>
                    </dd>
                </div>
                @if($lastDb->file_size_mb)
                <div class="flex justify-between">
                    <dt class="text-gray-500">اندازه فایل</dt>
                    <dd class="text-gray-800">{{ number_format($lastDb->file_size_mb, 1) }} MB</dd>
                </div>
                @endif
                @if($lastDb->duration_seconds)
                <div class="flex justify-between">
                    <dt class="text-gray-500">مدت</dt>
                    <dd class="text-gray-800">{{ $lastDb->duration_seconds }}s</dd>
                </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-gray-500">فایل‌های Arvan</dt>
                    <dd class="text-gray-800">{{ count($arvanDbFiles) }}</dd>
                </div>
            </dl>
            @else
            <p class="text-sm text-gray-400 italic">هیچ بکاپ دیتابیسی ثبت نشده</p>
            @endif
        </div>

        {{-- Storage Backup --}}
        <div class="p-5 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">بکاپ Storage</span>
                <span class="px-2.5 py-1 text-xs font-semibold rounded-full border {{ $healthColor($storageHealth) }}">
                    {{ $healthLabel($storageHealth) }}
                </span>
            </div>
            @if($lastStorage)
            <dl class="space-y-1.5 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">آخرین بکاپ</dt>
                    <dd class="text-gray-800 font-medium">
                        {{ $storageAge ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">سن بکاپ</dt>
                    <dd class="font-medium
                        {{ $lastStorage->completed_at?->lt(now()->subDays(7)) ? 'text-amber-600' : 'text-green-600' }}">
                        @if($lastStorage->completed_at)
                        {{ \App\Helpers\Jalali::formatFa($lastStorage->completed_at) }}
                        @else
                        —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">وضعیت</dt>
                    <dd>
                        <span class="px-2 py-0.5 rounded text-xs font-medium
                            {{ $lastStorage->isSuccess() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $lastStorage->isSuccess() ? 'موفق' : 'ناموفق' }}
                        </span>
                    </dd>
                </div>
                @if($lastStorage->file_size_mb)
                <div class="flex justify-between">
                    <dt class="text-gray-500">اندازه فایل</dt>
                    <dd class="text-gray-800">{{ number_format($lastStorage->file_size_mb, 1) }} MB</dd>
                </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-gray-500">فایل‌های Arvan</dt>
                    <dd class="text-gray-800">{{ count($arvanStorageFiles) }}</dd>
                </div>
            </dl>
            @else
            <p class="text-sm text-gray-400 italic">هیچ بکاپ Storage ثبت نشده — بکاپ هفتگی است</p>
            @endif
        </div>

    </div>
</div>
