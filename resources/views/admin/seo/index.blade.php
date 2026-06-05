@extends('layouts.admin')

@section('header', 'سئو پیشرفته: ' . $site->title)

@section('content')
<div class="max-w-4xl">
    <div class="mb-4">
        <a href="{{ route('admin.sites.index') }}" class="text-indigo-600 hover:underline">← بازگشت به سایت‌ها</a>
    </div>

    {{-- خلاصه --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-2xl font-bold text-indigo-600">{{ $avgScore }}</div>
            <div class="text-gray-500 text-sm">میانگین امتیاز صفحات</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <div class="text-2xl font-bold text-gray-700">{{ $pagesScored }}</div>
            <div class="text-gray-500 text-sm">صفحه امتیازدهی‌شده</div>
        </div>
    </div>

    {{-- مدیر ریدایرکت --}}
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="font-bold text-lg mb-4">ریدایرکت‌ها</h2>
        <form method="POST" action="{{ route('admin.sites.seo.redirects.store', $site) }}" class="flex flex-wrap gap-3 items-end mb-4">
            @csrf
            <div class="min-w-[120px]">
                <label class="form-label text-xs">از آدرس</label>
                <input type="text" name="from_url" class="form-control" placeholder="/صفحه-قدیمی" required>
            </div>
            <div class="min-w-[120px]">
                <label class="form-label text-xs">به آدرس</label>
                <input type="text" name="to_url" class="form-control" placeholder="/صفحه-جدید" required>
            </div>
            <div>
                <label class="form-label text-xs">کد</label>
                <select name="status_code" class="form-control">
                    <option value="301">۳۰۱ (دائمی)</option>
                    <option value="302">۳۰۲ (موقت)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">افزودن</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-2 text-right">از</th>
                        <th class="p-2 text-right">به</th>
                        <th class="p-2 text-right">کد</th>
                        <th class="p-2 text-right">وضعیت</th>
                        <th class="p-2 text-right">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($redirects as $r)
                        <tr class="border-t">
                            <td class="p-2 font-mono text-xs">{{ $r->from_url }}</td>
                            <td class="p-2 font-mono text-xs">{{ $r->to_url }}</td>
                            <td class="p-2">{{ $r->status_code }}</td>
                            <td class="p-2">{{ $r->is_active ? 'فعال' : 'غیرفعال' }}</td>
                            <td class="p-2">
                                <a href="#" class="text-indigo-600 text-xs" onclick="document.getElementById('edit-{{ $r->id }}').classList.toggle('hidden'); return false;">ویرایش</a>
                                <form method="POST" action="{{ route('admin.sites.seo.redirects.destroy', [$site, $r]) }}" class="inline" onsubmit="return confirm('حذف این ریدایرکت؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 text-xs">حذف</button>
                                </form>
                            </td>
                        </tr>
                        <tr id="edit-{{ $r->id }}" class="hidden bg-gray-50">
                            <td colspan="5" class="p-3">
                                <form method="POST" action="{{ route('admin.sites.seo.redirects.update', [$site, $r]) }}" class="flex flex-wrap gap-3 items-end">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="from_url" value="{{ $r->from_url }}" class="form-control min-w-[150px]" required>
                                    <input type="text" name="to_url" value="{{ $r->to_url }}" class="form-control min-w-[150px]" required>
                                    <select name="status_code" class="form-control w-32">
                                        <option value="301" {{ $r->status_code == 301 ? 'selected' : '' }}>۳۰۱</option>
                                        <option value="302" {{ $r->status_code == 302 ? 'selected' : '' }}>۳۰۲</option>
                                    </select>
                                    <label class="flex items-center gap-1">
                                        <input type="checkbox" name="is_active" value="1" {{ $r->is_active ? 'checked' : '' }}>
                                        <span class="text-sm">فعال</span>
                                    </label>
                                    <button type="submit" class="btn btn-primary">ذخیره</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-gray-500 text-center">ریدایرکتی ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($redirects->hasPages())
            <div class="mt-3">{{ $redirects->links() }}</div>
        @endif
    </div>

    <p class="text-gray-500 text-sm">ردیابی کلمات کلیدی و امتیاز صفحات در نسخه‌های بعدی اضافه می‌شود. ریدایرکت‌ها در customer-site باید توسط middleware یا وب‌سرور اعمال شوند.</p>
</div>
@endsection
