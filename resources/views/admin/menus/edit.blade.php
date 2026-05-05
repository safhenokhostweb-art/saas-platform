@extends('layouts.admin')

@section('header', 'ویرایش منو: ' . $menu->name)

@section('content')
<div class="max-w-4xl">
    <div class="mb-4 flex justify-between items-center">
        <a href="{{ route('admin.sites.menus.index', $site) }}" class="text-indigo-600 hover:underline">← بازگشت به منوها</a>
        <form method="POST" action="{{ route('admin.sites.menus.update', [$site, $menu]) }}" class="inline">
            @csrf
            @method('PUT')
            <input type="text" name="name" value="{{ old('name', $menu->name) }}" class="form-control inline-block w-48" placeholder="نام منو">
            <select name="location" class="form-control inline-block w-32">
                @foreach(\App\Models\Menu::locations() as $val => $label)
                    <option value="{{ $val }}" {{ old('location', $menu->location) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary">ذخیره تغییرات منو</button>
        </form>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    {{-- فرم افزودن آیتم --}}
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h3 class="font-bold mb-4">افزودن آیتم منو</h3>
        <form method="POST" action="{{ route('admin.sites.menus.items.store', [$site, $menu]) }}" class="flex flex-wrap gap-3 items-end">
            @csrf
            <div>
                <label class="form-label text-xs">عنوان</label>
                <input type="text" name="title" class="form-control" required placeholder="مثلاً صفحه اصلی">
            </div>
            <div>
                <label class="form-label text-xs">آدرس (URL)</label>
                <input type="text" name="url" class="form-control" placeholder="/ یا https://...">
            </div>
            <div>
                <label class="form-label text-xs">نوع</label>
                <select name="type" class="form-control">
                    @foreach(\App\Models\MenuItem::types() as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label text-xs">باز شدن</label>
                <select name="target" class="form-control">
                    <option value="_self">همان پنجره</option>
                    <option value="_blank">پنجره جدید</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">افزودن</button>
        </form>
    </div>

    {{-- لیست آیتم‌ها --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <h3 class="font-bold p-4 border-b">آیتم‌های منو</h3>
        @if($menu->items->isEmpty())
            <p class="p-6 text-gray-500 text-center">هنوز آیتمی اضافه نشده است.</p>
        @else
            <ul class="divide-y">
                @foreach($menu->items as $item)
                    @include('admin.menus.partials.item', ['item' => $item])
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
