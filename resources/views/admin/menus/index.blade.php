@extends('layouts.admin')

@section('header', 'منوهای سایت: ' . $site->title)

@section('content')
@include('admin.menus.guide')
<div class="max-w-4xl">
    <div class="flex justify-between items-center mb-4">
        <a href="{{ route('admin.sites.index') }}" class="text-indigo-600 hover:underline">← بازگشت به سایت‌ها</a>
        <a href="{{ route('admin.sites.menus.create', $site) }}" class="btn btn-primary">افزودن منو</a>
    </div>

    @if($menus->isEmpty())
        <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
            <p>هنوز منویی ایجاد نشده است.</p>
            <a href="{{ route('admin.sites.menus.create', $site) }}" class="btn btn-primary mt-4">افزودن اولین منو</a>
        </div>
    @else
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-right">نام</th>
                        <th class="p-3 text-right">موقعیت</th>
                        <th class="p-3 text-right">تعداد آیتم</th>
                        <th class="p-3 text-right">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($menus as $menu)
                        <tr class="border-t hover:bg-gray-50">
                            <td class="p-3 font-medium">{{ $menu->name }}</td>
                            <td class="p-3">{{ \App\Models\Menu::locations()[$menu->location] ?? $menu->location }}</td>
                            <td class="p-3">{{ $menu->all_items_count ?? 0 }}</td>
                            <td class="p-3">
                                <a href="{{ route('admin.sites.menus.edit', [$site, $menu]) }}" class="text-indigo-600 hover:underline">ویرایش</a>
                                <form method="POST" action="{{ route('admin.sites.menus.destroy', [$site, $menu]) }}" class="inline" onsubmit="return confirm('حذف این منو؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline mr-2">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
