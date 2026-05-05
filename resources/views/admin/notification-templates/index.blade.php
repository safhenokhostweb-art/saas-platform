@extends('layouts.admin')

@section('header', 'قالب‌های اعلان خوش‌آمدگویی')

@section('content')
<div class="max-w-4xl mx-auto py-5">
    <p class="text-gray-600 mb-4">
        <a href="{{ route('admin.access.index') }}" class="text-indigo-600 hover:underline">دسترسی و منو</a>
        →
        <a href="{{ route('admin.sites.notifications.create-bulk') }}" class="text-indigo-600 hover:underline">اعلان</a>
        →
        قالب‌های خوش‌آمدگویی
    </p>

    <div class="card p-4 shadow-sm rounded-xl mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="font-bold mb-1">اعلان‌های خودکار برای سایت‌های جدید</h5>
                <p class="text-sm text-gray-600 mb-0">این قالب‌ها به‌صورت خودکار به هر سایت جدید (پس از ایجاد و راه‌اندازی) ارسال می‌شوند.</p>
            </div>
            <a href="{{ route('admin.notification-templates.create') }}" class="btn btn-primary">قالب جدید</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>مخاطب</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $t)
                        <tr>
                            <td>{{ $t->title }}</td>
                            <td><span class="badge {{ ($t->audience ?? 'admin') === 'admin' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">{{ ($t->audience ?? 'admin') === 'admin' ? 'ادمین' : 'کاربر نهایی' }}</span></td>
                            <td>{{ $t->is_active ? 'فعال' : 'غیرفعال' }}</td>
                            <td>
                                <a href="{{ route('admin.notification-templates.edit', $t) }}" class="btn btn-sm btn-outline-primary">ویرایش</a>
                                <form method="POST" action="{{ route('admin.notification-templates.destroy', $t) }}" class="d-inline" onsubmit="return confirm('حذف این قالب؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-gray-500 py-4">هنوز قالبی تعریف نشده است. <a href="{{ route('admin.notification-templates.create') }}" class="text-indigo-600">اولین قالب را ایجاد کنید</a></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
