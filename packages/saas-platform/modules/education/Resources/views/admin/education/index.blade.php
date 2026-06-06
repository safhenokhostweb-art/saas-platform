@extends(config('education.layouts.admin', 'layouts.admin'))

@section('title', 'مرکز آموزش')

@section('content')
<div class="space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">مرکز آموزش و راهنما</h1>
            <p class="mt-1 text-sm text-gray-500">مدیریت محتوا، کشف ویژگی‌ها، آمار پذیرش و توصیه‌های یادگیری</p>
        </div>
        <a href="{{ route('admin.education.articles.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            مقاله جدید
        </a>
    </div>

    {{-- Content Stats Row --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
        @foreach([
            ['label' => 'منتشرشده',    'value' => $stats['articles'],       'color' => 'text-cyan-600',    'bg' => 'bg-cyan-50'],
            ['label' => 'پیش‌نویس',    'value' => $stats['drafts'],         'color' => 'text-amber-600',   'bg' => 'bg-amber-50'],
            ['label' => 'خودکار',      'value' => $stats['auto_generated'], 'color' => 'text-violet-600',  'bg' => 'bg-violet-50'],
            ['label' => 'دستی',        'value' => $stats['manual'],         'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50'],
            ['label' => 'راهنماها',    'value' => $stats['walkthroughs'],   'color' => 'text-blue-600',    'bg' => 'bg-blue-50'],
            ['label' => 'مسیر یادگیری','value' => $stats['learning_paths'],'color' => 'text-rose-600',    'bg' => 'bg-rose-50'],
        ] as $s)
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm text-center">
            <div class="text-2xl font-bold {{ $s['color'] }}">{{ number_format($s['value']) }}</div>
            <div class="mt-1 text-xs text-gray-500">{{ $s['label'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Feature Discovery + Adoption Row --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- Feature Discovery Widget --}}
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">کشف ویژگی‌های پلتفرم</h2>
                <a href="{{ route('admin.education.features.index') }}"
                   class="text-xs text-cyan-600 hover:underline">مشاهده همه</a>
            </div>
            <div class="flex items-center gap-6 text-center">
                <div>
                    <div class="text-3xl font-bold text-gray-900">{{ number_format($featureStats['total']) }}</div>
                    <div class="text-xs text-gray-500 mt-1">کل ویژگی‌های کشف‌شده</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-emerald-600">{{ number_format($featureStats['documented']) }}</div>
                    <div class="text-xs text-gray-500 mt-1">مستندشده</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-amber-600">{{ number_format($featureStats['undocumented']) }}</div>
                    <div class="text-xs text-gray-500 mt-1">بدون مستندات</div>
                </div>
            </div>
            @if($featureStats['total'] > 0)
            <div class="mt-3">
                @php $docPct = $featureStats['total'] > 0 ? round($featureStats['documented'] / $featureStats['total'] * 100) : 0; @endphp
                <div class="mb-1 flex justify-between text-xs text-gray-400">
                    <span>پوشش مستندات ویژگی‌ها</span>
                    <span class="font-semibold {{ $docPct >= 70 ? 'text-emerald-600' : ($docPct >= 40 ? 'text-amber-600' : 'text-red-500') }}">{{ $docPct }}%</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full {{ $docPct >= 70 ? 'bg-emerald-500' : ($docPct >= 40 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $docPct }}%"></div>
                </div>
            </div>
            @else
            <p class="mt-3 text-xs text-gray-400">هنوز ویژگی‌ای کشف نشده. دستور <code>education:discover-features</code> را اجرا کنید.</p>
            @endif
        </div>

        {{-- Adoption Stats Widget --}}
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">آمار پذیرش ویژگی‌ها</h2>
                <a href="{{ route('admin.education.adoption.index') }}"
                   class="text-xs text-cyan-600 hover:underline">گزارش کامل</a>
            </div>
            <div class="flex items-center gap-6 text-center">
                <div>
                    <div class="text-3xl font-bold text-gray-900">{{ number_format($adoptionStats['adopted'] ?? 0) }}</div>
                    <div class="text-xs text-gray-500 mt-1">ویژگی‌های استفاده‌شده</div>
                </div>
                <div>
                    <div class="text-3xl font-bold {{ ($adoptionStats['adoption_pct'] ?? 0) >= 60 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $adoptionStats['adoption_pct'] ?? 0 }}%</div>
                    <div class="text-xs text-gray-500 mt-1">نرخ پذیرش</div>
                </div>
                <div>
                    <div class="text-3xl font-bold text-rose-500">{{ number_format($recommendationCount) }}</div>
                    <div class="text-xs text-gray-500 mt-1">توصیه فعال</div>
                </div>
            </div>
            @php $adPct = $adoptionStats['adoption_pct'] ?? 0; @endphp
            <div class="mt-3">
                <div class="mb-1 flex justify-between text-xs text-gray-400">
                    <span>نرخ پذیرش کلی</span>
                    <span class="font-semibold {{ $adPct >= 60 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $adPct }}%</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full {{ $adPct >= 60 ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ min($adPct, 100) }}%"></div>
                </div>
            </div>
        </div>

    </div>

    {{-- Module Coverage --}}
    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-gray-700">پوشش مستندسازی ماژول‌ها</h2>
        <div class="flex items-center gap-4">
            <div class="flex-1">
                <div class="mb-1 flex justify-between text-xs text-gray-500">
                    <span>{{ $coverage['covered'] }} از {{ $coverage['total_modules'] }} ماژول مستند شده</span>
                    <span class="{{ $coverage['coverage_pct'] >= 80 ? 'text-emerald-600' : ($coverage['coverage_pct'] >= 50 ? 'text-amber-600' : 'text-red-600') }} font-semibold">
                        {{ $coverage['coverage_pct'] }}%
                    </span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full {{ $coverage['coverage_pct'] >= 80 ? 'bg-emerald-500' : ($coverage['coverage_pct'] >= 50 ? 'bg-amber-500' : 'bg-red-500') }} transition-all"
                         style="width: {{ $coverage['coverage_pct'] }}%"></div>
                </div>
            </div>
        </div>
        @if(! empty($coverage['uncovered_slugs']))
        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach($coverage['uncovered_slugs'] as $slug)
            <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200">{{ $slug }}</span>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Quick Navigation --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-9">
        @foreach([
            ['route' => 'admin.education.articles.index',               'label' => 'مقالات',           'color' => 'bg-cyan-50 text-cyan-700 hover:bg-cyan-100'],
            ['route' => 'admin.education.categories.index',             'label' => 'دسته‌بندی‌ها',      'color' => 'bg-slate-50 text-slate-700 hover:bg-slate-100'],
            ['route' => 'admin.education.walkthroughs.index',           'label' => 'راهنماها',          'color' => 'bg-violet-50 text-violet-700 hover:bg-violet-100'],
            ['route' => 'admin.education.learning-paths.index',         'label' => 'مسیر یادگیری',     'color' => 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'],
            ['route' => 'admin.education.release-notes.index',          'label' => 'یادداشت انتشار',   'color' => 'bg-orange-50 text-orange-700 hover:bg-orange-100'],
            ['route' => 'admin.education.features.index',               'label' => 'ویژگی‌ها',          'color' => 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'],
            ['route' => 'admin.education.recommendations.index',        'label' => 'توصیه‌ها',          'color' => 'bg-pink-50 text-pink-700 hover:bg-pink-100'],
            ['route' => 'admin.education.adoption.index',               'label' => 'آمار پذیرش',       'color' => 'bg-teal-50 text-teal-700 hover:bg-teal-100'],
            ['route' => 'admin.education.generated-content.review',     'label' => 'بررسی AI',          'color' => 'bg-amber-50 text-amber-700 hover:bg-amber-100'],
        ] as $nav)
        <a href="{{ route($nav['route']) }}"
           class="rounded-xl border border-transparent px-3 py-3 text-center text-xs font-medium transition-colors {{ $nav['color'] }}">
            {{ $nav['label'] }}
        </a>
        @endforeach
    </div>

</div>
@endsection
