@extends('layouts.admin')

@section('header', 'لایسنس‌های ماژول')

@section('content')
<form method="get" class="mb-4 flex flex-wrap gap-2 items-end">
    <select name="site_id" class="form-control w-auto">
        <option value="">همهٔ سایت‌ها</option>
        @foreach($sites as $s)
            <option value="{{ $s->id }}" {{ request('site_id') == $s->id ? 'selected' : '' }}>{{ $s->title }} ({{ $s->domain }})</option>
        @endforeach
    </select>
    <select name="module_id" class="form-control w-auto">
        <option value="">همهٔ ماژول‌ها</option>
        @foreach($modules as $m)
            <option value="{{ $m->id }}" {{ request('module_id') == $m->id ? 'selected' : '' }}>{{ $m->title }}</option>
        @endforeach
    </select>
    <select name="status" class="form-control w-auto">
        <option value="">همهٔ وضعیت‌ها</option>
        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>فعال</option>
        <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>منقضی</option>
        <option value="revoked" {{ request('status') === 'revoked' ? 'selected' : '' }}>لغو شده</option>
    </select>
    <button type="submit" class="btn btn-secondary">فیلتر</button>
</form>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-right">سایت</th>
                <th class="p-3 text-right">ماژول</th>
                <th class="p-3 text-right">نوع</th>
                <th class="p-3 text-right">تاریخ خرید</th>
                <th class="p-3 text-right">انقضا</th>
                <th class="p-3 text-right">وضعیت</th>
                <th class="p-3 text-right">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($licenses as $license)
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3">{{ $license->site->title ?? '—' }} <span class="text-gray-500">({{ $license->site->domain ?? '—' }})</span></td>
                    <td class="p-3">{{ $license->module->title ?? '—' }}</td>
                    <td class="p-3">{{ $license->license_type === 'subscription' ? 'اشتراک' : 'یک‌بار' }}</td>
                    <td class="p-3">{{ $license->purchased_at ? \App\Helpers\Jalali::formatFa($license->purchased_at, 'Y/m/d') : '—' }}</td>
                    <td class="p-3">{{ $license->expires_at ? \App\Helpers\Jalali::formatFa($license->expires_at, 'Y/m/d') : 'بدون انقضا' }}</td>
                    <td class="p-3">
                        @if($license->isActive())
                            <span class="text-green-600">فعال</span>
                        @elseif($license->isRevoked())
                            <span class="text-red-600">لغو شده</span>
                        @else
                            <span class="text-gray-500">منقضی</span>
                        @endif
                    </td>
                    <td class="p-3">
                        @if($license->isActive())
                            <form action="{{ route('admin.module-licenses.revoke', $license) }}" method="post" class="inline" onsubmit="return confirm('لغو این لایسنس؟');">
                                @csrf
                                <button type="submit" class="text-red-600 hover:underline">لغو</button>
                            </form>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="p-6 text-center text-gray-500">لایسنس یافت نشد.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-3 border-t">{{ $licenses->links() }}</div>
</div>

<h3 class="text-lg font-bold mt-8 mb-2">اعطای لایسنس تبلیغاتی</h3>
<form method="post" action="{{ route('admin.module-licenses.grant') }}" class="max-w-xl space-y-2">
    @csrf
    <div class="flex flex-wrap gap-2 items-center">
        <select name="site_id" class="form-control w-auto" required>
            <option value="">انتخاب سایت</option>
            @foreach($sites as $s)
                <option value="{{ $s->id }}">{{ $s->title }} ({{ $s->domain }})</option>
            @endforeach
        </select>
        <select name="module_id" class="form-control w-auto" required>
            <option value="">انتخاب ماژول</option>
            @foreach($modules as $m)
                <option value="{{ $m->id }}">{{ $m->title }}</option>
            @endforeach
        </select>
        <input type="text" name="expires_at" class="form-control w-40" placeholder="انقضا (شمسی Y/m/d)" dir="ltr">
        <button type="submit" class="btn btn-primary">اعطا</button>
    </div>
</form>
@endsection
