@extends('layouts.admin')

@section('header', 'اعلان‌های سایت — ' . $site->title)

@section('content')
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="{{ route('admin.sites.index') }}" class="text-indigo-600 hover:underline">سایت‌ها</a>
        →
        <a href="{{ route('admin.sites.edit', $site) }}" class="text-indigo-600 hover:underline">{{ $site->title }}</a>
        → اعلان‌ها
    </p>

    <div class="card p-4 shadow-sm rounded-xl mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h5 class="font-bold mb-0">اعلان‌های ارسالی از پلتفرم</h5>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('admin.sites.notifications.create', $site) }}" class="btn btn-primary">اعلان جدید (این سایت)</a>
                <a href="{{ route('admin.sites.notifications.create-bulk') }}" class="btn btn-outline-secondary">ارسال به چند سایت</a>
                <a href="{{ route('admin.notification-templates.index') }}" class="btn btn-outline-secondary">قالب‌های خوش‌آمدگویی</a>
            </div>
        </div>
        <p class="text-sm text-gray-600 mb-4">با انتخاب مخاطب «ادمین» اعلان در پنل مدیریت سایت مشتری (تولبار شناور) دیده می‌شود؛ با «کاربران نهایی» در فرانت و نوار «حساب من» (در صورت فعال بودن ماژول اعلان روی آن سایت). از صفحهٔ <a href="{{ route('admin.sites.edit', $site) }}" class="text-indigo-600">ویرایش سایت</a> یا <a href="{{ route('admin.sites.modules.edit', $site) }}" class="text-indigo-600">ماژول‌ها</a> ماژول را فعال کنید.</p>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>اولویت</th>
                        <th>مخاطب</th>
                        <th>موقعیت</th>
                        <th>تصویر</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $n)
                        <tr>
                            <td>{{ $n->title }}</td>
                            <td>@php
                                $p = $n->priority ?? 'normal';
                                $labels = ['low' => 'کم', 'normal' => 'عادی', 'high' => 'مهم', 'urgent' => 'فوری'];
                                $classes = ['low' => 'bg-gray-100 text-gray-700', 'normal' => 'bg-sky-100 text-sky-800', 'high' => 'bg-amber-100 text-amber-800', 'urgent' => 'bg-red-100 text-red-800'];
                            @endphp
                                <span class="badge {{ $classes[$p] ?? 'bg-sky-100 text-sky-800' }}">{{ $labels[$p] ?? 'عادی' }}</span></td>
                            <td>
                                @if(($n->audience ?? 'admin') === 'public')
                                    <span class="badge bg-emerald-100 text-emerald-800">کاربران سایت</span>
                                @else
                                    <span class="badge bg-amber-100 text-amber-800">ادمین</span>
                                @endif
                            </td>
                            <td>{{ $n->position === 'top' ? 'بالا' : ($n->position === 'left' ? 'چپ' : 'راست') }}</td>
                            <td>{{ $n->image_path ? '✓' : '—' }}</td>
                            <td>{{ $n->is_active ? 'فعال' : 'غیرفعال' }}</td>
                            <td>
                                <a href="{{ route('admin.sites.notifications.edit', [$site, $n]) }}" class="btn btn-sm btn-outline-primary">ویرایش</a>
                                <form method="POST" action="{{ route('admin.sites.notifications.destroy', [$site, $n]) }}" class="d-inline" onsubmit="return confirm('حذف این اعلان؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-gray-500 py-4">هنوز اعلانی ارسال نشده است. <a href="{{ route('admin.sites.notifications.create', $site) }}" class="text-indigo-600">اولین اعلان را ایجاد کنید</a></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($notifications->hasPages())
            <div class="mt-3">{{ $notifications->links() }}</div>
        @endif
    </div>
</div>
@endsection
