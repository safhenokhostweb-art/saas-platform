@extends('layouts.admin')

@section('header', 'داشبورد')


@section('content')

@php
    $modulesCount = $modulesCount ?? 0;
    $activeLicensesCount = $activeLicensesCount ?? 0;
@endphp
@push("head")
<style>
.glass-card{background:rgba(255,255,255,0.72);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.25);box-shadow:0 8px 32px rgba(15,23,42,0.06)}
.glass-card:hover{box-shadow:0 12px 48px rgba(15,23,42,0.1)}
.glass-card-strong{background:rgba(255,255,255,0.85);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,0.4);box-shadow:0 8px 32px rgba(15,23,42,0.08)}
.glass-table thead th{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:.75rem 1rem;background:rgba(99,102,241,0.06);border-bottom:2px solid rgba(99,102,241,0.15);white-space:nowrap}
.glass-table tbody td{padding:.75rem 1rem;border-bottom:1px solid rgba(226,232,240,0.5);font-size:.875rem}
.glass-table tbody tr:hover{background:rgba(99,102,241,0.03)}
.badge-glass{display:inline-flex;align-items:center;padding:.25rem .75rem;border-radius:9999px;font-size:.75rem;font-weight:600;gap:.375rem}
.badge-glass--success{background:rgba(16,185,129,0.15);color:#059669}
.badge-glass--danger{background:rgba(239,68,68,0.15);color:#dc2626}
.stat-tile{position:relative;display:block;overflow:hidden;border-radius:1rem;padding:1.25rem;box-shadow:0 8px 24px rgba(15,23,42,0.08);transition:all .3s;color:#fff!important;text-decoration:none;border:1px solid rgba(255,255,255,0.15)}
.stat-tile:hover{transform:translateY(-6px) scale(1.02);box-shadow:0 20px 48px rgba(15,23,42,0.18)}
.stat-tile--violet{background:linear-gradient(135deg,#7c3aed,#1e1b4b)}
.stat-tile--sky{background:linear-gradient(135deg,#0284c7,#0f172a)}
.stat-tile--emerald{background:linear-gradient(135deg,#059669,#022c22)}
.stat-tile--rose{background:linear-gradient(135deg,#e11d48,#4c0519)}
.stat-tile--amber{background:linear-gradient(135deg,#d97706,#451a03)}
.stat-tile--indigo{background:linear-gradient(135deg,#6366f1,#1e1b4b)}
.pulse-dot{display:inline-block;width:8px;height:8px;border-radius:50%;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.6;transform:scale(1.2)}}
.progress-glass{height:.5rem;border-radius:9999px;background:rgba(226,232,240,0.5);overflow:hidden}
.progress-glass__bar{height:100%;border-radius:9999px;background:linear-gradient(135deg,#6366f1,#a855f7)}
.scrollbar-thin::-webkit-scrollbar{width:6px;height:6px}
.scrollbar-thin::-webkit-scrollbar-thumb{background:rgba(148,163,184,0.3);border-radius:8px}
</style>
@endpush
<div class="glass-card overflow-hidden mb-8">
<div class="absolute inset-0 bg-gradient-to-l from-indigo-600/10 via-purple-600/5 to-transparent pointer-events-none"></div>
<div class="relative z-10 p-6 sm:p-8">
<h2 class="text-2xl sm:text-3xl font-bold text-gray-900">خوش آمدید، <span class="bg-gradient-to-l from-indigo-600 to-purple-600 bg-clip-text text-transparent">{{ auth()->user()->name ?? 'مدیر' }}</span> 👋</h2>
<p class="text-gray-500 text-sm mt-2">آخرین وضعیت پلتفرم در زیر نمایش داده شده است.</p>
</div>
</div>





<p class="text-slate-600 text-sm mb-4">آمار و میانبرهای اصلی پلتفرم — هر کاشی را برای ورود به بخش مربوطه بزنید.</p>

<div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-8">
    <a href="{{ route('admin.customers.index') }}" class="stat-tile stat-tile--violet">
        <span class="stat-tile__shine" aria-hidden="true"></span>
        <div class="stat-tile__label">مشتری‌ها</div>
    </a>
    <a href="{{ route('admin.sites.index') }}" class="stat-tile stat-tile--sky">
        <span class="stat-tile__shine" aria-hidden="true"></span>
        <div class="stat-tile__label">سایت‌ها (تِننت)</div>


        <div class="stat-tile__value">{{ \App\Helpers\NumberHelper::formatFa($sites) }}</div>
        <div class="stat-tile__hint">سایت‌های مشتری</div>
    </a>
    <a href="{{ route('admin.forms.index') }}" class="stat-tile stat-tile--emerald">
        <span class="stat-tile__shine" aria-hidden="true"></span>
        <div class="stat-tile__label">فرم‌ها</div>
    </a>
    <a href="{{ route('admin.modules.index') }}" class="stat-tile stat-tile--rose">
        <span class="stat-tile__shine" aria-hidden="true"></span>
        <div class="stat-tile__label">تعریف ماژول</div>

        <div class="stat-tile__value">{{ \App\Helpers\NumberHelper::formatFa($modulesCount) }}</div>
        <div class="stat-tile__hint">کاتالوگ ماژول</div>
    </a>
    <a href="{{ route('admin.licenses.index') }}" class="stat-tile stat-tile--amber">
        <span class="stat-tile__shine" aria-hidden="true"></span>
        <div class="stat-tile__label">لایسنس فعال</div>
        <div class="stat-tile__value">{{ \App\Helpers\NumberHelper::formatFa($activeLicensesCount) }}</div>
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
    <a href="{{ route('admin.platform-settings.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm hover:border-indigo-300 hover:shadow transition">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 text-lg">⚙</span>
        <span class="font-medium text-slate-800">تنظیمات پلتفرم</span>
    </a>
    <a href="{{ route('admin.updates.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm hover:border-indigo-300 hover:shadow transition">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-100 text-sky-700 text-lg">↻</span>
        <span class="font-medium text-slate-800">بروزرسانی‌ها</span>
    </a>
    <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm hover:border-indigo-300 hover:shadow transition">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-100 text-violet-700 text-lg">👤</span>
        <span class="font-medium text-slate-800">کاربران</span>
    </a>
    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm hover:border-indigo-300 hover:shadow transition">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700 text-lg">⌂</span>
        <span class="font-medium text-slate-800">داشبورد کاربر عادی</span>
    </a>
</div>

<!-- هشدارهای انقضا (اعلان خودکار در ادمین اصلی) -->
@if(isset($expiryReminders) && $expiryReminders->isNotEmpty())
<div class="glass-card-strong overflow-hidden p-6 mb-8 border-r-4 border-r-amber-400">
    <h2 class="font-bold mb-2">هشدارهای انقضا</h2>
    <p class="text-gray-500 text-sm mb-4">اعلان خودکار انقضای پشتیبانی، هاست و دامنه (۱ ماه قبل و هر ۱۰ روز) — در <a href="{{ route('admin.platform-settings.index') }}" class="text-indigo-600 hover:underline">تنظیمات پلتفرم</a> می‌توانید ارسال را فعال/غیرفعال کنید.</p>
    <ul class="space-y-2 max-h-64 overflow-y-auto">
        @foreach($expiryReminders as $reminder)
            <li class="flex items-start gap-2 p-3 rounded-lg bg-amber-50 border border-amber-100">
                <span class="shrink-0 w-2 h-2 mt-1.5 rounded-full {{ $reminder->type === 'support' ? 'bg-blue-500' : ($reminder->type === 'host' ? 'bg-purple-500' : 'bg-teal-500') }}" title="{{ $reminder->type === 'support' ? 'پشتیبانی' : ($reminder->type === 'host' ? 'هاست' : 'دامنه') }}"></span>
                <div class="min-w-0 flex-1">
                    <span class="font-medium text-gray-800">{{ \App\Helpers\NumberHelper::toPersianNumbers($reminder->title ?? 'هشدار انقضا') }}</span>
                    @if($reminder->body)
                        <p class="text-sm text-gray-600 mt-0.5">{{ \App\Helpers\NumberHelper::toPersianNumbers(Str::limit($reminder->body, 100)) }}</p>
                    @endif
                    <p class="text-xs text-gray-500 mt-1">{{ $reminder->sent_at ? \App\Helpers\Jalali::formatFa($reminder->sent_at, 'Y/m/d H:i') : '' }}</p>
                </div>
                @if($reminder->site)
                    <a href="{{ route('admin.sites.edit', $reminder->site) }}" class="shrink-0 text-sm text-indigo-600 hover:underline">ویرایش سایت</a>
                @endif
            </li>
        @endforeach
    </ul>
</div>
@endif

<!-- فرم‌ها و وضعیت در سایت‌ها (فقط در پنل ادمین) -->
@if(isset($formSiteColumnExists))
@php
    $formsList = $formsList ?? collect();
    $sitesList = $sitesList ?? collect();
@endphp
<div class="glass-card-strong overflow-hidden p-6 mb-8">
    <h2 class="font-bold mb-4">فرم‌ها و فعال/غیرفعال در سایت مشتری</h2>
    @if(!$formSiteColumnExists)
        <div class="rounded-xl bg-red-50/80 border border-red-200 p-4 backdrop-blur-sm">
            ستون <code>is_active</code> در جدول <code>form_site</code> یافت نشد. برای نمایش این بخش در ترمینال اجرا کنید:
            <pre class="mt-2 p-3 bg-gray-100 rounded text-sm">php artisan migrate</pre>
        </div>
    @else
        <p class="text-gray-500 text-sm mb-4">برای هر فرم می‌توانید در هر سایت مشتری آن را فعال یا غیرفعال کنید. فرم غیرفعال در آن سایت نمایش داده نمی‌شود.</p>
        <div class="overflow-x-auto scrollbar-thin rounded-xl border border-gray-100">
            <table class="w-full text-right border-collapse glass-table">
                <thead>
                    <tr class="border-b">
                        <th class="p-3 font-bold text-xs uppercase tracking-wider text-gray-500">فرم</th>
                        @foreach($sitesList as $site)
                            <th class="p-3 font-bold whitespace-nowrap">{{ \App\Helpers\NumberHelper::toPersianNumbers($site->title) }}</th>
                        @endforeach
                        <th class="p-3 font-bold text-xs uppercase tracking-wider text-gray-500">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($formsList as $form)
                        <tr class="border-b hover:bg-indigo-50/30 transition-colors">
                            <td class="p-3">{{ \App\Helpers\NumberHelper::toPersianNumbers($form->title) }}</td>
                            @foreach($sitesList as $site)
                                @php
                                    $pivot = $form->sites->firstWhere('id', $site->id);
                                    $isActive = $pivot ? (bool) ($pivot->pivot->is_active ?? true) : false;
                                @endphp
                                <td class="p-3">
                                    <form action="{{ route('admin.forms.sites.toggle', [$form, $site]) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium {{ $isActive ? 'badge-glass badge-glass--success' : 'bg-gray-100/80 text-gray-500 hover:bg-gray-200/80' }}" title="{{ $isActive ? 'غیرفعال کردن' : 'فعال کردن' }}">
                                            @if($isActive)
                                                <span aria-hidden="true">✓</span> فعال
                                            @else
                                                <span aria-hidden="true">—</span> غیرفعال
                                            @endif
                                        </button>
                                    </form>
                                </td>
                            @endforeach
                            <td class="p-3">
                                <a href="{{ route('admin.forms.sites.edit', $form) }}" class="text-blue-600 hover:underline text-sm">تنظیم سایت‌ها</a>
                                <span class="mx-1">|</span>
                                <a href="{{ route('admin.forms.edit', $form) }}" class="text-blue-600 hover:underline text-sm">ویرایش فرم</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($sitesList) + 2 }}" class="text-center text-gray-400 py-8">هنوز فرمی تعریف نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endif

<!-- وضعیت ماژول‌های سایت‌ها (چک‌لیست امکاناتی که به هر سایت مشتری داده شده) -->
@if(isset($sitesWithModules) && isset($allModules))
<div class="glass-card-strong overflow-hidden p-6 mb-8">
    <h2 class="font-bold mb-2">وضعیت ماژول‌های سایت‌های مشتری</h2>
    <p class="text-gray-500 text-sm mb-4">ماژول‌هایی که از پنل ادمین به هر سایت داده شده و در API لایسنس/سایت‌کانفیگ به آن سایت برگردانده می‌شوند. برای تغییر، از منوی سایت‌ها وارد هر سایت شوید و ماژول‌ها را مدیریت کنید.</p>
    @if($allModules->isEmpty())
        <p class="text-gray-500 text-sm">هنوز ماژولی در سیستم تعریف نشده است. از منوی ماژول‌ها یا سایت‌ها ماژول اضافه کنید.</p>
    @else
        <div class="overflow-x-auto scrollbar-thin rounded-xl border border-gray-100">
            <table class="w-full text-right border-collapse glass-table">
                <thead>
                    <tr class="border-b">
                        <th class="p-3 font-bold text-xs uppercase tracking-wider text-gray-500">سایت</th>
                        <th class="p-3 font-bold text-xs uppercase tracking-wider text-gray-500">دامنه</th>
                        @foreach($allModules as $mod)
                            <th class="p-3 font-bold whitespace-nowrap" title="{{ $mod->title ?? $mod->slug }}">{{ $mod->slug }}</th>
                        @endforeach
                        <th class="p-3 font-bold text-xs uppercase tracking-wider text-gray-500">وضعیت سایت</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sitesWithModules as $site)
                        <tr class="border-b hover:bg-indigo-50/30 transition-colors">
                            <td class="p-3">
                                <a href="{{ route('admin.sites.show', $site) }}" class="text-blue-600 hover:underline">{{ \App\Helpers\NumberHelper::toPersianNumbers($site->title) }}</a>
                                <span class="mx-1">|</span>
                                <a href="{{ route('admin.sites.modules.edit', $site) }}" class="text-sm text-gray-600 hover:underline">ماژول‌ها</a>
                            </td>
                            <td class="p-3 text-sm text-gray-600">{{ \App\Helpers\NumberHelper::toPersianNumbers($site->domain ?? '—') }}</td>
                            @foreach($allModules as $mod)
                                @php
                                    $attached = $site->modules->contains('id', $mod->id);
                                @endphp
                                <td class="p-3">
                                    @if($attached)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-sm badge-glass badge-glass--success" title="فعال برای این سایت">✓</span>
                                    @else
                                        <span class="text-gray-300" title="غیرفعال">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="p-3">
                                @if($site->is_active)
                                    <span class="text-green-600 text-sm">فعال</span>
                                @else
                                    <span class="text-red-600 text-sm">غیرفعال</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $allModules->count() + 3 }}" class="text-center text-gray-400 py-8">هنوز سایتی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endif

@include('admin.dashboard.guide')




<div class="glass-card-strong overflow-hidden mb-8">
<div class="p-6">
<div class="flex items-center gap-3 mb-4">
<span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 text-lg">📊</span>
<h2 class="font-bold text-gray-900 text-lg">آمار کلی سیستم</h2>
</div>
<div class="chart-container relative w-full" style="min-height:300px">
<canvas id="statsChart"></canvas>
</div>
</div>
</div>


<div class="glass-card overflow-hidden">
<div class="p-6">
<div class="flex items-center justify-between flex-wrap gap-4">
<div class="flex items-center gap-3">
<span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 text-gray-600">💚</span>
<div>
<p class="font-semibold text-gray-800">وضعیت سیستم</p>
<p class="text-sm text-gray-500">همه بخش‌ها به صورت عادی در حال فعالیت هستند</p>
</div>
</div>
<div class="flex items-center gap-2"><span class="pulse-dot bg-green-500"></span><span class="text-sm font-medium text-green-700">فعال</span></div>
</div>
</div>
</div>

@endsection

@section('scripts')

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
var ctx=document.getElementById('statsChart').getContext('2d');
new Chart(ctx,{type:'bar',data:{labels:['مشتری‌ها','سایت‌ها','فرم‌ها'],datasets:[{label:'آمار سیستم',data:@json([$customers,$sites,$forms]),backgroundColor:['rgba(124,58,237,0.7)','rgba(2,132,199,0.7)','rgba(5,150,105,0.7)'],borderColor:['rgba(124,58,237,1)','rgba(2,132,199,1)','rgba(5,150,105,1)'],borderWidth:2,borderRadius:6,barPercentage:0.6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(255,255,255,0.9)',titleColor:'#1e293b',bodyColor:'#475569',borderColor:'rgba(226,232,240,1)',borderWidth:1,cornerRadius:8,rtl:true,titleFont:{family:'Vazirmatn'},bodyFont:{family:'Vazirmatn'}}}},scales:{y:{beginAtZero:true,grid:{color:'rgba(226,232,240,0.4)'},ticks:{font:{family:'Vazirmatn'}}},x:{grid:{display:false},ticks:{font:{family:'Vazirmatn'}}}}}});
});
</script>
@endsection
