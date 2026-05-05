@extends('layouts.admin')

@section('header', 'افزودن منو')

@section('content')
<div class="max-w-2xl">
    <div class="mb-4">
        <a href="{{ route('admin.sites.menus.index', $site) }}" class="text-indigo-600 hover:underline">← بازگشت به منوها</a>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <form method="POST" action="{{ route('admin.sites.menus.store', $site) }}">
            @csrf
            <div class="mb-4">
                <label class="form-label">نام منو</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="مثلاً منوی اصلی" required>
                @error('name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label">موقعیت</label>
                <select name="location" class="form-control">
                    @foreach(\App\Models\Menu::locations() as $val => $label)
                        <option value="{{ $val }}" {{ old('location', 'header') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">ذخیره</button>
        </form>
    </div>
</div>
@endsection
