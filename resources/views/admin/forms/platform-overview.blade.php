@extends('layouts.admin')

@section('title', 'مدیریت ماژول فرم‌ساز')

@section('content')
<div class="min-h-screen bg-[var(--ui-bg)] p-6">

    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-[var(--ui-text)]">ماژول فرم‌ساز</h1>
                <p class="text-sm text-[var(--ui-text-muted)]">نمای کلی — مدیریت فرم‌ها در پنل هر سایت مشتری انجام می‌شود</p>
            </div>
        </div>
    </div>

    {{-- Architecture Notice --}}
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-8 flex gap-4">
        <div class="flex-shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-amber-800 mb-1">معماری صحیح (ADR-015)</p>
            <p class="text-sm text-amber-700">
                طبق تصمیم معماری، هر سایت مشتری فرم‌های خود را به‌صورت کاملاً مستقل در پنل ادمین خودش ایجاد و مدیریت می‌کند.
                پلتفرم اصلی فقط نمای آماری ارائه می‌دهد. برای مدیریت فرم‌ها، به پنل ادمین سایت مشتری مراجعه کنید.
            </p>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-[var(--ui-surface)] rounded-2xl border border-[var(--ui-border)] p-5">
            <p class="text-xs text-[var(--ui-text-muted)] mb-1">سایت‌های دارای فرم‌ساز فعال</p>
            <p class="text-3xl font-bold text-indigo-600">{{ $enabledCount }}</p>
            <p class="text-xs text-[var(--ui-text-muted)] mt-1">از {{ $sitesWithForms->count() }} سایت فعال</p>
        </div>
        <div class="bg-[var(--ui-surface)] rounded-2xl border border-[var(--ui-border)] p-5">
            <p class="text-xs text-[var(--ui-text-muted)] mb-1">سایت‌های بدون فرم‌ساز</p>
            <p class="text-3xl font-bold text-[var(--ui-text-muted)]">{{ $sitesWithForms->count() - $enabledCount }}</p>
            <p class="text-xs text-[var(--ui-text-muted)] mt-1">ماژول غیرفعال یا نصب نشده</p>
        </div>
        <div class="bg-[var(--ui-surface)] rounded-2xl border border-[var(--ui-border)] p-5">
            <p class="text-xs text-[var(--ui-text-muted)] mb-1">محل مدیریت</p>
            <p class="text-base font-semibold text-green-600">پنل هر سایت مشتری</p>
            <p class="text-xs text-[var(--ui-text-muted)] mt-1">admin/forms در customer-site</p>
        </div>
    </div>

    {{-- Sites Table --}}
    <div class="bg-[var(--ui-surface)] rounded-2xl border border-[var(--ui-border)] overflow-hidden">
        <div class="px-6 py-4 border-b border-[var(--ui-border)] flex items-center justify-between">
            <h2 class="text-base font-semibold text-[var(--ui-text)]">وضعیت ماژول فرم‌ساز — همه سایت‌ها</h2>
            <span class="text-xs text-[var(--ui-text-muted)]">فقط نمایش — بدون امکان ویرایش</span>
        </div>

        @if($sitesWithForms->isEmpty())
            <div class="px-6 py-16 text-center">
                <svg class="w-10 h-10 text-[var(--ui-text-muted)] mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z" />
                </svg>
                <p class="text-[var(--ui-text-muted)] text-sm">هیچ سایت فعالی یافت نشد</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-[var(--ui-bg)] border-b border-[var(--ui-border)]">
                            <th class="px-6 py-3 text-right text-xs font-medium text-[var(--ui-text-muted)]">سایت مشتری</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-[var(--ui-text-muted)]">دامنه</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-[var(--ui-text-muted)]">وضعیت ماژول</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-[var(--ui-text-muted)]">نسخه</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-[var(--ui-text-muted)]">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-border)]">
                        @foreach($sitesWithForms as $row)
                        <tr class="hover:bg-[var(--ui-bg)] transition-colors">
                            <td class="px-6 py-3">
                                <span class="font-medium text-[var(--ui-text)]">{{ $row['site']->title ?? '—' }}</span>
                            </td>
                            <td class="px-6 py-3 text-[var(--ui-text-muted)] font-mono text-xs">
                                {{ $row['site']->domain ?? '—' }}
                            </td>
                            <td class="px-6 py-3">
                                @if($row['enabled'])
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                        فعال
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[var(--ui-bg)] text-[var(--ui-text-muted)] text-xs font-medium border border-[var(--ui-border)]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                        غیرفعال
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-[var(--ui-text-muted)] text-xs">
                                {{ $row['version'] ?? '—' }}
                            </td>
                            <td class="px-6 py-3">
                                @if($row['site']->domain)
                                    <a href="https://{{ $row['site']->domain }}/admin/forms"
                                       target="_blank"
                                       class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                        پنل فرم‌ها
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                    </a>
                                @else
                                    <span class="text-xs text-[var(--ui-text-muted)]">دامنه تنظیم نشده</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection
