@extends('layouts.admin')

@section('header', 'مدیریت سایت‌ها')

@section('content')
<x-module-features module='sites' />
<div class='mb-4 flex items-center gap-2'>
    <a href="{{ route('admin.onboarding.create') }}" class='btn btn-primary flex items-center gap-1.5'>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        راه‌اندازی سایت جدید
    </a>
</div>

@if(isset($filterCustomer) || isset($filterModule))
<div class='mb-4 p-3 bg-blue-50 rounded border border-blue-200 text-sm'>
    فیلتر فعال:
    @if(isset($filterCustomer)) مشتری: <strong>{{ $filterCustomer->name ?? $filterCustomer->company ?? '—' }}</strong> @endif
    @if(isset($filterModule)) ماژول: <strong>{{ $filterModule->title ?? $filterModule->slug }}</strong> @endif
    <a href="{{ route('admin.sites.index') }}" class='mr-2 text-blue-600 hover:underline'>حذف فیلتر</a>
</div>
@endif

<div class='bg-white rounded shadow overflow-hidden'>
    <div class='overflow-x-auto'>
        <table class='w-full text-sm'>
            <thead class='bg-gray-50'>
                <tr>
                    <th class='p-3 text-right'>#</th>
                    <th class='p-3 text-right'>عنوان</th>
                    <th class='p-3 text-right'>مشتری</th>
                    <th class='p-3 text-right'>دامنه</th>
                    <th class='p-3 text-right'>وضعیت</th>
                    <th class='p-3 text-right'>ماژول‌ها</th>
                    <th class='p-3 text-right'>تاریخ ایجاد</th>
                    <th class='p-3 text-right'>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sites as $site)
                <tr class='border-t hover:bg-gray-50'>
                    <td class='p-3'>{{ $site->id }}</td>
                    <td class='p-3 font-medium'>{{ $site->title }}</td>
                    <td class='p-3'>{{ $site->customer?->name ?? $site->customer?->company ?? '—' }}</td>
                    <td class='p-3' dir='ltr'>{{ $site->domain ?? '—' }}</td>
                    <td class='p-3'>
                        @if($site->is_active)
                            <span class='px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700'>فعال</span>
                        @else
                            <span class='px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700'>غیرفعال</span>
                        @endif
                    </td>
                    <td class='p-3'>
                        @php $activeModules = $site->modules->where('pivot.is_active', true); @endphp
                        @if($activeModules->count() > 0)
                            <div class='flex flex-wrap gap-1'>
                                @foreach($activeModules->take(3) as $module)
                                    <span class='text-xs px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded'>{{ $module->title ?? $module->slug }}</span>
                                @endforeach
                                @if($activeModules->count() > 3)
                                    <span class='text-xs text-gray-500'>+{{ $activeModules->count() - 3 }}</span>
                                @endif
                            </div>
                        @else
                            <span class='text-gray-400'>—</span>
                        @endif
                    </td>
                    <td class='p-3 text-gray-500'>{{ \App\Helpers\Jalali::formatFa($site->created_at, 'Y/m/d') }}</td>
                    <td class='p-3'>
                        <a href="{{ route('admin.sites.edit', $site) }}" class='text-blue-600 hover:underline text-xs'>ویرایش</a>
                        <a href="{{ route('admin.sites.license.create', $site) }}" class='text-indigo-600 hover:underline text-xs mr-2'>صدور لایسنس</a>
                        <button
                            type="button"
                            onclick="confirmDeleteSite({{ $site->id }}, '{{ addslashes($site->title) }}')"
                            class='text-red-600 hover:underline text-xs mr-2'>
                            حذف
                        </button>
                    </td>
                </tr>
                @empty
                <tr><td colspan='8' class='p-4 text-center text-gray-500'>هیچ سایتی یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@include('admin.sites.guide')

{{-- Modal تأیید حذف سرتاسری --}}
<div id="deleteSiteModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50" dir="rtl">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-gray-900">حذف سرتاسری سایت</h3>
                <p class="text-xs text-gray-500">این عملیات قابل بازگشت نیست</p>
            </div>
        </div>
        <p class="text-sm text-gray-700 mb-2">
            آیا مطمئن هستید که می‌خواهید سایت <strong id="deleteSiteName" class="text-red-700"></strong> را حذف کنید؟
        </p>
        <ul class="text-xs text-gray-500 mb-5 space-y-1 border border-gray-100 rounded-lg p-3 bg-gray-50">
            <li>• سایت به همراه ماژول‌ها، تنظیمات و اعلان‌هایش کامل حذف می‌شود</li>
            <li>• نصب‌های مرتبط به وضعیت «حذف‌شده» تغییر می‌کنند (لاگ باقی می‌ماند)</li>
            <li>• سوابق لایسنس، خرید و فعالیت برای حسابرسی نگه داشته می‌شوند، بدون اتصال به سایت</li>
        </ul>
        <div class="flex gap-3 justify-end">
            <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">
                انصراف
            </button>
            <form id="deleteSiteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700">
                    بله، حذف کن
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDeleteSite(id, name) {
    document.getElementById('deleteSiteName').textContent = name;
    document.getElementById('deleteSiteForm').action = '/admin/sites/' + id;
    var modal = document.getElementById('deleteSiteModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeDeleteModal() {
    var modal = document.getElementById('deleteSiteModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
document.getElementById('deleteSiteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>
@endsection
