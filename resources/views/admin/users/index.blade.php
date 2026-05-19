@extends('layouts.admin')

@section('header', 'مدیریت کاربران پلتفرم')

@section('content')
@include('admin.users.guide')

<x-module-features module="users" />

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-end gap-2">
        <div>
            <label class="block text-xs text-gray-500 mb-1">موبایل</label>
            <input type="text" name="mobile" class="form-control w-36" placeholder="موبایل" value="{{ $filters['mobile'] ?? '' }}">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">ایمیل</label>
            <input type="text" name="email" class="form-control w-36" placeholder="ایمیل" value="{{ $filters['email'] ?? '' }}">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">نام</label>
            <input type="text" name="name" class="form-control w-36" placeholder="نام" value="{{ $filters['name'] ?? '' }}">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">وضعیت</label>
            <select name="status" class="form-select w-32">
                <option value="">همه</option>
                <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>فعال</option>
                <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>غیرفعال</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">نقش</label>
            <select name="role" class="form-select w-32">
                <option value="">همه</option>
                @foreach(\App\Models\Role::orderBy('name')->get() as $role)
                    <option value="{{ $role->slug }}" {{ ($filters['role'] ?? '') === $role->slug ? 'selected' : '' }}>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">جستجو</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">پاک کردن</a>
    </form>
    <div class="flex gap-2">
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">+ کاربر جدید</a>
        <a href="{{ route('admin.users.export-csv') }}" class="btn btn-outline-secondary">خروجی CSV</a>
        <a href="{{ route('admin.users.import') }}" class="btn btn-outline-secondary">وارد کردن</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="p-3 bg-gray-50 border-b flex items-center gap-2" id="bulk-actions" style="display:none;">
        <span class="text-sm text-gray-600" id="selected-count">۰ انتخاب شده</span>
        <button type="button" class="btn btn-sm btn-primary" onclick="bulkAction('activate')">فعال‌سازی گروهی</button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="bulkAction('deactivate')">غیرفعال‌سازی گروهی</button>
        <button type="button" class="btn btn-sm btn-danger" onclick="bulkAction('delete')">حذف گروهی</button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="p-3 w-10"><input type="checkbox" id="select-all" class="form-check-input"></th>
                    <th class="p-3 font-bold cursor-pointer" onclick="sortBy('id')"># @if(($sortBy ?? 'created_at') === 'id') {{ $sortDir === 'asc' ? '↑' : '↓' }} @endif</th>
                    <th class="p-3 font-bold cursor-pointer" onclick="sortBy('mobile')">موبایل @if(($sortBy ?? '') === 'mobile') {{ $sortDir === 'asc' ? '↑' : '↓' }} @endif</th>
                    <th class="p-3 font-bold">ایمیل</th>
                    <th class="p-3 font-bold">نام (پروفایل)</th>
                    <th class="p-3 font-bold">نقش‌ها</th>
                    <th class="p-3 font-bold cursor-pointer" onclick="sortBy('created_at')">تاریخ ثبت‌نام @if(($sortBy ?? 'created_at') === 'created_at') {{ $sortDir === 'asc' ? '↑' : '↓' }} @endif</th>
                    <th class="p-3 font-bold">وضعیت</th>
                    <th class="p-3 font-bold">مشتری</th>
                    <th class="p-3 font-bold">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="p-3"><input type="checkbox" class="user-checkbox form-check-input" value="{{ $user->id }}"></td>
                        <td class="p-3">{{ $user->id }}</td>
                        <td class="p-3 font-medium">{{ $user->mobile }}</td>
                        <td class="p-3 text-sm text-gray-600">{{ $user->email ?? '—' }}</td>
                        <td class="p-3">{{ $user->profile->full_name ?? '—' }}</td>
                        <td class="p-3">
                            @foreach($user->roles as $role)
                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded ml-1">{{ $role->name }}</span>
                            @endforeach
                            @if($user->roles->isEmpty()) — @endif
                        </td>
                        <td class="p-3 text-sm text-gray-600">{{ $user->created_at ? \App\Helpers\Jalali::formatFa($user->created_at, 'Y/m/d H:i') : '—' }}</td>
                        <td class="p-3">
                            @if($user->id !== auth()->id())
                                <form action="{{ route('admin.users.toggle', $user) }}" method="POST" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="px-2 py-1 rounded text-sm {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' }}">
                                        {{ $user->is_active ? 'فعال' : 'غیرفعال' }}
                                    </button>
                                </form>
                            @else
                                <span class="text-gray-500 text-sm">شما</span>
                            @endif
                        </td>
                        <td class="p-3 text-sm">
                            @if($user->customer)
                                <a href="{{ route('admin.customers.edit', $user->customer) }}" class="text-blue-600 hover:underline">{{ $user->customer->company_name ?: $user->customer->name }}</a>
                            @else — @endif
                        </td>
                        <td class="p-3 space-x-2 space-x-reverse">
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-blue-600 hover:underline text-sm">ویرایش</a>
                            @if($user->is_admin)
                                <span class="text-xs bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded">ادمین</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="p-6 text-center text-gray-500">کاربری یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3 border-t border-gray-200">
        {{ $users->appends(request()->query())->links() }}
    </div>
</div>

<form id="bulk-form" method="POST" style="display:none;">
    @csrf
    <input type="hidden" name="action" id="bulk-action-input">
</form>

<script>
document.getElementById('select-all')?.addEventListener('change', function() {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
    updateBulkBar();
});
document.querySelectorAll('.user-checkbox').forEach(cb => {
    cb.addEventListener('change', updateBulkBar);
});
function updateBulkBar() {
    var checked = document.querySelectorAll('.user-checkbox:checked');
    var bar = document.getElementById('bulk-actions');
    var count = document.getElementById('selected-count');
    if (checked.length > 0) {
        bar.style.display = 'flex';
        count.textContent = checked.length + ' انتخاب شده';
    } else {
        bar.style.display = 'none';
    }
}
function bulkAction(action) {
    var checked = document.querySelectorAll('.user-checkbox:checked');
    if (checked.length === 0) return;
    if (action === 'delete' && !confirm(checked.length + ' کاربر حذف شوند؟')) return;
    var ids = Array.from(checked).map(cb => cb.value);
    var form = document.getElementById('bulk-form');
    document.getElementById('bulk-action-input').value = JSON.stringify(ids);
    form.action = action === 'activate' || action === 'deactivate'
        ? '{{ route('admin.users.bulk-toggle') }}'
        : '{{ route('admin.users.bulk-delete') }}';
    form.submit();
}
function sortBy(field) {
    var url = new URL(window.location.href);
    var dir = url.searchParams.get('sort') === field && url.searchParams.get('direction') === 'asc' ? 'desc' : 'asc';
    url.searchParams.set('sort', field);
    url.searchParams.set('direction', dir);
    window.location.href = url.toString();
}
</script>
@endsection
