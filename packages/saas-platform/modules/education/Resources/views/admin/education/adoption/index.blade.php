@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', 'گزارش پذیرش ویژگی‌ها')

@section('content')
<div class="space-y-5" dir="rtl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">گزارش پذیرش ویژگی‌ها</h1>
            <p class="text-sm text-gray-500 mt-0.5">آمار استفاده از ویژگی‌های پلتفرم توسط کاربران ادمین</p>
        </div>
        <a href="{{ route('admin.education.index') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">بازگشت</a>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm text-center">
            <div class="text-4xl font-bold text-gray-900">{{ number_format($totalFeatures) }}</div>
            <div class="mt-1 text-sm text-gray-500">کل ویژگی‌های کشف‌شده</div>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm text-center">
            <div class="text-4xl font-bold text-emerald-600">{{ number_format($stats['adopted'] ?? 0) }}</div>
            <div class="mt-1 text-sm text-gray-500">ویژگی‌های استفاده‌شده</div>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm text-center">
            <div class="text-4xl font-bold {{ ($stats['adoption_pct'] ?? 0) >= 60 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $stats['adoption_pct'] ?? 0 }}%</div>
            <div class="mt-1 text-sm text-gray-500">نرخ پذیرش</div>
        </div>
    </div>

    {{-- Adoption Bar --}}
    @php $pct = min($stats['adoption_pct'] ?? 0, 100); @endphp
    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-2 flex justify-between text-sm text-gray-600">
            <span>پذیرش کلی ویژگی‌ها</span>
            <span class="font-semibold {{ $pct >= 60 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $pct }}%</span>
        </div>
        <div class="h-3 w-full overflow-hidden rounded-full bg-gray-100">
            <div class="h-full rounded-full {{ $pct >= 60 ? 'bg-emerald-500' : 'bg-amber-500' }} transition-all" style="width: {{ $pct }}%"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">

        {{-- Top Features by Usage --}}
        <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-700">پراستفاده‌ترین ویژگی‌ها</h2>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($topFeatures as $i => $feature)
                <div class="flex items-center justify-between px-5 py-3">
                    <div class="flex items-center gap-3">
                        <span class="w-5 text-xs text-gray-400 text-center">{{ $i + 1 }}</span>
                        <div>
                            <span class="inline-flex items-center rounded-md bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-600 ml-1">{{ $feature->module_slug }}</span>
                            <span class="text-sm text-gray-700">{{ $feature->feature_slug }}</span>
                        </div>
                    </div>
                    <span class="text-sm font-semibold text-emerald-600">{{ number_format($feature->total_usage) }}</span>
                </div>
                @empty
                <div class="px-5 py-8 text-center text-sm text-gray-400">هنوز داده‌ای ثبت نشده.</div>
                @endforelse
            </div>
        </div>

        {{-- Unused Features --}}
        <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">ویژگی‌های استفاده‌نشده</h2>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ $unusedFeatures->count() }}</span>
            </div>
            <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                @forelse($unusedFeatures as $feature)
                <div class="flex items-center justify-between px-5 py-2.5">
                    <div>
                        <span class="inline-flex items-center rounded-md bg-gray-50 px-1.5 py-0.5 text-xs font-medium text-gray-500 ml-1">{{ $feature->module_slug }}</span>
                        <span class="text-sm text-gray-700">{{ $feature->feature_name }}</span>
                    </div>
                    @if($feature->route_name)
                    <span class="text-xs text-gray-400 font-mono">{{ $feature->route_name }}</span>
                    @endif
                </div>
                @empty
                <div class="px-5 py-8 text-center text-sm text-emerald-600">همه ویژگی‌ها حداقل یک بار استفاده شده‌اند.</div>
                @endforelse
            </div>
        </div>

    </div>

</div>
@endsection
