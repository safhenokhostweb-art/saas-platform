@php
$totalFiles    = $arvanStatus['total_files'];
$totalSizeMb   = $arvanStatus['total_size_mb'];
$latestAgo     = $arvanStatus['latest_upload_ago'];
$lastVerified  = $arvanStatus['last_verified_at'];
$dbCount       = $arvanStatus['db_file_count'];
$storageCount  = $arvanStatus['storage_file_count'];
$capAvailable  = $arvanStatus['capacity_available'];

$sizeLabel = $totalSizeMb >= 1024
    ? round($totalSizeMb / 1024, 2) . ' GB'
    : $totalSizeMb . ' MB';
@endphp

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
        <svg class="w-5 h-5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
        </svg>
        <h2 class="text-sm font-semibold text-gray-700">وضعیت ذخیره‌سازی Arvan</h2>
        @if($totalFiles > 0)
        <span class="mr-auto px-2 py-0.5 rounded-full text-xs font-semibold bg-cyan-100 text-cyan-700">
            {{ $totalFiles }} فایل
        </span>
        @else
        <span class="mr-auto px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">
            بدون فایل
        </span>
        @endif
    </div>

    <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-4">

        {{-- Total Files --}}
        <div class="space-y-0.5">
            <dt class="text-xs text-gray-500">کل فایل‌ها</dt>
            <dd class="text-lg font-bold text-gray-900">
                {{ $totalFiles > 0 ? number_format($totalFiles) : '—' }}
            </dd>
            @if($dbCount > 0 || $storageCount > 0)
            <dd class="text-xs text-gray-400">DB: {{ $dbCount }} / Storage: {{ $storageCount }}</dd>
            @endif
        </div>

        {{-- Used Space --}}
        <div class="space-y-0.5">
            <dt class="text-xs text-gray-500">فضای استفاده‌شده</dt>
            @if($totalSizeMb > 0)
            <dd class="text-lg font-bold text-gray-900">{{ $sizeLabel }}</dd>
            @else
            <dd class="text-sm font-semibold text-gray-400">NOT AVAILABLE</dd>
            <dd class="text-xs text-gray-400">اندازه فایل‌ها دریافت نشد</dd>
            @endif
        </div>

        {{-- Latest Upload --}}
        <div class="space-y-0.5">
            <dt class="text-xs text-gray-500">آخرین آپلود</dt>
            @if($latestAgo)
            <dd class="text-sm font-semibold text-gray-900">{{ $latestAgo }}</dd>
            <dd class="text-xs text-gray-400">{{ \App\Helpers\Jalali::diffForHumansFa(\Carbon\Carbon::createFromTimestamp($arvanStatus['latest_upload_ts'])) }}</dd>
            @else
            <dd class="text-sm font-semibold text-gray-400">NOT AVAILABLE</dd>
            <dd class="text-xs text-gray-400">هیچ فایلی یافت نشد</dd>
            @endif
        </div>

        {{-- Last Sync --}}
        <div class="space-y-0.5">
            <dt class="text-xs text-gray-500">آخرین sync</dt>
            @if($latestAgo)
            <dd class="text-sm font-semibold text-gray-900">{{ $latestAgo }}</dd>
            @else
            <dd class="text-sm font-semibold text-gray-400">NOT AVAILABLE</dd>
            @endif
        </div>

        {{-- Latest Verification --}}
        <div class="space-y-0.5">
            <dt class="text-xs text-gray-500">آخرین تأیید یکپارچگی</dt>
            @if($lastVerified)
            @php $verifiedAt = \Carbon\Carbon::parse($lastVerified); @endphp
            <dd class="text-sm font-semibold text-gray-900">{{ $verifiedAt->diffForHumans() }}</dd>
            <dd class="text-xs text-gray-400">{{ \App\Helpers\Jalali::diffForHumansFa($verifiedAt) }}</dd>
            @else
            <dd class="text-sm font-semibold text-gray-400">NOT AVAILABLE</dd>
            <dd class="text-xs text-gray-400">هنوز بررسی یکپارچگی انجام نشده</dd>
            @endif
        </div>

        {{-- Bucket Capacity --}}
        <div class="space-y-0.5">
            <dt class="text-xs text-gray-500">ظرفیت bucket</dt>
            <dd class="text-sm font-semibold text-gray-400">NOT AVAILABLE</dd>
            <dd class="text-xs text-gray-400">API Arvan ظرفیت bucket را برنمی‌گرداند</dd>
        </div>

    </div>

    @if($totalFiles === 0)
    <div class="px-5 pb-4">
        <div class="px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
            هیچ فایلی در مسیرهای پلتفرم در Arvan یافت نشد. ممکن است sync هنوز انجام نشده باشد.
        </div>
    </div>
    @endif
</div>
