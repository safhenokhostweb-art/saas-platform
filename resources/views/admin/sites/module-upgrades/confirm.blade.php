@extends('layouts.admin')

@section('content')
<h1 class="text-xl font-bold mb-4">تأیید بروزرسانی ماژول</h1>

<div class="border p-4 rounded mb-4 bg-amber-50 border-amber-200">
    <p><strong>سایت:</strong> {{ $site->domain }}</p>
    <p><strong>ماژول:</strong> {{ $upgrade['title'] }}</p>
    <p><strong>نسخهٔ فعلی:</strong> {{ $upgrade['installed_version'] ?? '—' }}</p>
    <p><strong>نسخهٔ هدف:</strong> {{ $upgrade['available_version'] }}</p>
    <p class="text-sm text-amber-800 mt-2">
        این عملیات سایت را موقتاً در حالت نگهداری قرار می‌دهد، از دیتابیس نسخهٔ پشتیبان می‌گیرد، بستهٔ ماژول را نصب و مایگریشن‌ها را اجرا می‌کند.
    </p>
</div>

@if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 p-3 rounded mb-4">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.sites.module-upgrades.store', [$site, $upgrade['slug']]) }}">
    @csrf
    <div class="mb-4">
        <label class="block font-medium mb-1">دلیل بروزرسانی (الزامی)</label>
        <textarea name="reason" rows="3" class="w-full border rounded p-2" required minlength="5">{{ old('reason') }}</textarea>
    </div>
    <label class="flex items-center gap-2 mb-4">
        <input type="checkbox" name="confirmed_upgrade" value="1" required>
        <span>تأیید می‌کنم که از پیامدهای این بروزرسانی (قفل موقت سایت، اجرای مایگریشن) آگاه هستم.</span>
    </label>
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">تأیید و شروع بروزرسانی</button>
        <a href="{{ route('admin.sites.module-upgrades.index', $site) }}" class="btn btn-secondary">انصراف</a>
    </div>
</form>
@endsection
