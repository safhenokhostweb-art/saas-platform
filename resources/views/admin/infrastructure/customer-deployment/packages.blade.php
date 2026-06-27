@extends('layouts.admin')

@section('header', 'پکیج‌های نصب')

@section('content')

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">پکیج‌های نصب مشتریان</h1>
        <p class="text-sm text-gray-500">لیست تمام Release Package های ثبت‌شده برای استقرار</p>
    </div>
    <a href="{{ route('admin.infrastructure.customer-deployment.dashboard') }}" class="btn btn-outline-secondary text-sm flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        بازگشت
    </a>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
    @if($packages->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <svg class="w-14 h-14 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <p class="font-medium">هیچ پکیجی ثبت نشده</p>
            <p class="text-xs mt-1">با اجرای دستور <code class="bg-gray-100 px-1 rounded">php artisan install:register-package</code> پکیج اضافه کنید</p>
        </div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">نسخه</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">وضعیت</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">حجم</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">SHA256</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Manifest</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">تاریخ انتشار</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">یادداشت انتشار</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($packages as $pkg)
                <tr class="hover:bg-gray-50 transition-colors {{ $pkg->is_active ? 'bg-green-50/40' : '' }}">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-semibold text-gray-900">v{{ $pkg->version }}</span>
                            @if($pkg->is_active)
                                <span class="inline-flex items-center gap-1 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>فعال
                                </span>
                            @endif
                        </div>
                        @if($pkg->arvan_key)
                            <div class="text-xs text-gray-400 font-mono mt-0.5 truncate max-w-[180px]" title="{{ $pkg->arvan_key }}">
                                ArvanKey: {{ substr($pkg->arvan_key, 0, 16) }}…
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($pkg->is_active)
                            <span class="inline-flex items-center gap-1 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">فعال (production)</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded">آرشیو</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600 font-mono">
                        {{ $pkg->size_bytes ? $pkg->sizeHuman() : '—' }}
                    </td>
                    <td class="px-4 py-3">
                        @if($pkg->sha256)
                            <div class="flex items-center gap-1">
                                <code class="text-xs font-mono text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200 truncate max-w-[100px]" title="{{ $pkg->sha256 }}">{{ substr($pkg->sha256, 0, 12) }}…</code>
                                <button onclick="copyToClipboard('{{ $pkg->sha256 }}')" title="کپی SHA256" class="text-gray-300 hover:text-gray-600 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($pkg->manifest_checksum)
                            <code class="text-xs font-mono text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200 truncate max-w-[80px] block" title="{{ $pkg->manifest_checksum }}">{{ substr($pkg->manifest_checksum, 0, 10) }}…</code>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        {{ $pkg->released_at ? $pkg->released_at->format('Y/m/d H:i') : '—' }}
                        @if($pkg->released_at)
                            <div class="text-gray-400">{{ $pkg->released_at->diffForHumans() }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500 max-w-[200px]">
                        @if($pkg->release_notes)
                            <div class="truncate" title="{{ $pkg->release_notes }}">{{ $pkg->release_notes }}</div>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
            <p class="text-xs text-gray-400">
                برای ثبت پکیج جدید:
                <code class="bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded font-mono">php artisan install:register-package --version=x.x.x --key=ARVAN_KEY</code>
            </p>
            {{ $packages->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // brief visual feedback
        const el = event.currentTarget;
        el.classList.add('text-green-500');
        setTimeout(() => el.classList.remove('text-green-500'), 1200);
    });
}
</script>
@endpush

@endsection
