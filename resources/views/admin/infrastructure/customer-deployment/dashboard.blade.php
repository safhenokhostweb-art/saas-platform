@extends('layouts.admin')

@section('header', 'مرکز استقرار مشتریان')

@push('head')
<style>
.cie-stat{position:relative;overflow:hidden;border-radius:1rem;padding:1.25rem 1.5rem;color:#fff;display:block;transition:all .3s;border:1px solid rgba(255,255,255,0.1)}
.cie-stat--blue{background:linear-gradient(135deg,#2563eb,#1e3a8a)}
.cie-stat--green{background:linear-gradient(135deg,#059669,#064e3b)}
.cie-stat--red{background:linear-gradient(135deg,#dc2626,#7f1d1d)}
.cie-stat--amber{background:linear-gradient(135deg,#d97706,#451a03)}
.cie-stat--violet{background:linear-gradient(135deg,#7c3aed,#2e1065)}
.cie-stat--sky{background:linear-gradient(135deg,#0284c7,#082f49)}
.cie-stat__label{font-size:.8rem;font-weight:500;opacity:.85;margin-bottom:.4rem}
.cie-stat__value{font-size:2rem;font-weight:800;line-height:1}
.cie-stat__sub{font-size:.75rem;opacity:.7;margin-top:.4rem}
.status-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .65rem;border-radius:9999px;font-size:.75rem;font-weight:600}
.status-pending{background:#fef9c3;color:#92400e}
.status-running{background:#dbeafe;color:#1e40af}
.status-rolling_back{background:#ede9fe;color:#5b21b6}
.status-complete{background:#d1fae5;color:#065f46}
.status-failed{background:#fee2e2;color:#991b1b}
.status-rollback_failed{background:#fce7f3;color:#9d174d}
.status-rolled_back{background:#f3f4f6;color:#374151}
.cie-card{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 12px rgba(15,23,42,0.05);padding:1.25rem 1.5rem}
.cie-quick-btn{display:flex;align-items:center;gap:.75rem;padding:.875rem 1.25rem;border-radius:.875rem;border:1px solid #e2e8f0;background:#fff;text-decoration:none;color:#374151;transition:all .2s;box-shadow:0 1px 4px rgba(15,23,42,0.04)}
.cie-quick-btn:hover{border-color:#6366f1;background:#f5f3ff;color:#4f46e5;transform:translateX(-2px)}
.cie-quick-btn__icon{width:2.5rem;height:2.5rem;border-radius:.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.1rem}
</style>
@endpush

@section('content')

{{-- Header --}}
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">مرکز استقرار مشتریان</h1>
        <p class="text-sm text-gray-500">مدیریت نصب، پیگیری پیشرفت، مدیریت سرورها</p>
    </div>
    <a href="{{ route('admin.infrastructure.customer-deployment.wizard') }}" class="btn btn-primary text-sm">
        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        نصب جدید
    </a>
</div>

{{-- Stats Row --}}
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <a href="{{ route('admin.infrastructure.customer-deployment.history', ['status' => 'running']) }}" class="cie-stat cie-stat--blue">
        <div class="cie-stat__label">در حال اجرا</div>
        <div class="cie-stat__value">{{ $active->where('status', 'running')->count() }}</div>
    </a>
    <a href="{{ route('admin.infrastructure.customer-deployment.history', ['status' => 'pending']) }}" class="cie-stat cie-stat--amber">
        <div class="cie-stat__label">در صف</div>
        <div class="cie-stat__value">{{ $queued }}</div>
    </a>
    <a href="{{ route('admin.infrastructure.customer-deployment.history', ['status' => 'complete']) }}" class="cie-stat cie-stat--green">
        <div class="cie-stat__label">موفق</div>
        <div class="cie-stat__value">{{ $complete }}</div>
    </a>
    <a href="{{ route('admin.infrastructure.customer-deployment.history', ['status' => 'failed']) }}" class="cie-stat cie-stat--red">
        <div class="cie-stat__label">ناموفق</div>
        <div class="cie-stat__value">{{ $failed }}</div>
    </a>
    <a href="{{ route('admin.infrastructure.customer-deployment.servers.index') }}" class="cie-stat cie-stat--violet">
        <div class="cie-stat__label">سرورها</div>
        <div class="cie-stat__value">{{ $servers }}</div>
    </a>
    <a href="{{ route('admin.infrastructure.customer-deployment.packages') }}" class="cie-stat cie-stat--sky">
        <div class="cie-stat__label">پکیج‌ها</div>
        <div class="cie-stat__value">{{ $packages }}</div>
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Active Installations --}}
    <div class="lg:col-span-2">
        <div class="cie-card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-800">نصب‌های فعال</h2>
                @if($active->count() > 0)
                    <span class="status-badge status-running">{{ $active->count() }} فعال</span>
                @endif
            </div>

            @if($active->isEmpty())
                <div class="text-center py-10 text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                    <p class="text-sm">هیچ نصب فعالی وجود ندارد</p>
                    <a href="{{ route('admin.infrastructure.customer-deployment.wizard') }}" class="btn btn-primary btn-sm mt-3 inline-flex">شروع نصب جدید</a>
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($active as $inst)
                    <div class="py-3 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
                            @if($inst->status === 'running')
                            <svg class="w-5 h-5 text-indigo-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            @elseif($inst->status === 'rolling_back')
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                            @else
                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm text-gray-900 truncate">{{ $inst->customer_company }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ $inst->customer_domain }}</div>
                            @if($inst->current_stage)
                            <div class="text-xs text-indigo-600 mt-0.5">مرحله: {{ $inst->current_stage }}</div>
                            @endif
                        </div>
                        <div class="flex flex-col items-end gap-1 flex-shrink-0">
                            <span class="status-badge status-{{ $inst->status }}">
                                {{ match($inst->status) {
                                    'pending' => 'در صف',
                                    'running' => 'در حال اجرا',
                                    'rolling_back' => 'در حال بازگشت',
                                    default => $inst->status
                                } }}
                            </span>
                            <a href="{{ route('admin.infrastructure.customer-deployment.installations.show', $inst) }}" class="text-xs text-indigo-600 hover:underline">مشاهده</a>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Recent Installations --}}
        <div class="cie-card mt-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-gray-800">آخرین نصب‌ها</h2>
                <a href="{{ route('admin.infrastructure.customer-deployment.history') }}" class="text-xs text-indigo-600 hover:underline">همه تاریخچه</a>
            </div>

            @if($recent->isEmpty())
                <p class="text-sm text-gray-400 text-center py-6">هنوز نصبی انجام نشده</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-500 border-b border-gray-100">
                                <th class="pb-2 text-right font-medium">مشتری</th>
                                <th class="pb-2 text-right font-medium">دامنه</th>
                                <th class="pb-2 text-right font-medium">سرور</th>
                                <th class="pb-2 text-right font-medium">وضعیت</th>
                                <th class="pb-2 text-right font-medium">تاریخ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($recent as $inst)
                            <tr>
                                <td class="py-2.5">
                                    <a href="{{ route('admin.infrastructure.customer-deployment.installations.show', $inst) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $inst->customer_company }}</a>
                                </td>
                                <td class="py-2.5 text-gray-500 max-w-[120px] truncate">{{ $inst->customer_domain }}</td>
                                <td class="py-2.5 text-gray-500">{{ $inst->remoteServer?->label ?? '—' }}</td>
                                <td class="py-2.5">
                                    <span class="status-badge status-{{ $inst->status }}">
                                        {{ match($inst->status) {
                                            'pending' => 'در صف',
                                            'running' => 'در حال اجرا',
                                            'complete' => 'موفق',
                                            'failed' => 'ناموفق',
                                            'rolling_back' => 'بازگشت',
                                            'rolled_back' => 'بازگشت‌خورد',
                                            'rollback_failed' => 'خطای بازگشت',
                                            default => $inst->status
                                        } }}
                                    </span>
                                </td>
                                <td class="py-2.5 text-gray-400 text-xs">{{ $inst->created_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="space-y-4">
        <div class="cie-card">
            <h2 class="font-semibold text-gray-800 mb-4">دسترسی سریع</h2>
            <div class="space-y-2">
                <a href="{{ route('admin.infrastructure.customer-deployment.wizard') }}" class="cie-quick-btn">
                    <div class="cie-quick-btn__icon bg-indigo-50 text-indigo-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </div>
                    <div>
                        <div class="font-medium text-sm">نصب جدید</div>
                        <div class="text-xs text-gray-400">ویزارد ۸ مرحله‌ای</div>
                    </div>
                </a>
                <a href="{{ route('admin.infrastructure.customer-deployment.servers.index') }}" class="cie-quick-btn">
                    <div class="cie-quick-btn__icon bg-violet-50 text-violet-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </div>
                    <div>
                        <div class="font-medium text-sm">مدیریت سرورها</div>
                        <div class="text-xs text-gray-400">SSH، آزمایش اتصال</div>
                    </div>
                </a>
                <a href="{{ route('admin.infrastructure.customer-deployment.history') }}" class="cie-quick-btn">
                    <div class="cie-quick-btn__icon bg-sky-50 text-sky-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <div>
                        <div class="font-medium text-sm">تاریخچه نصب‌ها</div>
                        <div class="text-xs text-gray-400">جستجو، فیلتر، جزئیات</div>
                    </div>
                </a>
                <a href="{{ route('admin.infrastructure.customer-deployment.packages') }}" class="cie-quick-btn">
                    <div class="cie-quick-btn__icon bg-green-50 text-green-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                    </div>
                    <div>
                        <div class="font-medium text-sm">پکیج‌های انتشار</div>
                        <div class="text-xs text-gray-400">نسخه‌ها، SHA-256</div>
                    </div>
                </a>
            </div>
        </div>

        {{-- Install Mode Legend --}}
        <div class="cie-card">
            <h2 class="font-semibold text-gray-800 mb-3 text-sm">حالت‌های نصب</h2>
            <div class="space-y-2 text-sm">
                <div class="flex items-start gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded text-xs font-mono mt-0.5">FRESH</span>
                    <span class="text-gray-600 text-xs">نصب تمیز از صفر</span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 bg-green-50 text-green-700 rounded text-xs font-mono mt-0.5">RESTORE</span>
                    <span class="text-gray-600 text-xs">بازیابی از بکاپ (بدون DB)</span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 bg-amber-50 text-amber-700 rounded text-xs font-mono mt-0.5">CLONE</span>
                    <span class="text-gray-600 text-xs">کلون از سایت موجود</span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 bg-sky-50 text-sky-700 rounded text-xs font-mono mt-0.5">MOVE</span>
                    <span class="text-gray-600 text-xs">انتقال به سرور دیگر</span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-700 rounded text-xs font-mono mt-0.5">REINSTALL</span>
                    <span class="text-gray-600 text-xs">نصب مجدد کدها</span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
