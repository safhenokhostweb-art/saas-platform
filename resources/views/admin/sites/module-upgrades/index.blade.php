@extends('layouts.admin')

@section('content')
<h1 class="text-xl font-bold mb-4">بروزرسانی‌های موجود ماژول — {{ $site->domain }}</h1>

@if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 p-3 rounded mb-4">{{ session('error') }}</div>
@endif
@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 p-3 rounded mb-4">{{ session('success') }}</div>
@endif

@if(empty($updates))
    <p class="text-gray-600">در حال حاضر بروزرسانی موجودی برای ماژول‌های این سایت وجود ندارد.</p>
@else
    <div class="space-y-3">
        @foreach($updates as $u)
            <div class="border p-4 rounded flex items-center justify-between">
                <div>
                    <strong>{{ $u['title'] }}</strong>
                    <span class="text-sm text-gray-500">({{ $u['installed_version'] ?? '—' }} → {{ $u['available_version'] }})</span>
                    <span class="text-xs text-gray-400 mr-2">نوع: {{ $u['update_type'] }}</span>
                </div>
                @if($u['has_package'])
                    <a href="{{ route('admin.sites.module-upgrades.confirm', [$site, $u['slug']]) }}" class="btn btn-primary btn-sm">درخواست بروزرسانی</a>
                @else
                    <span class="text-xs text-amber-600">بستهٔ نصب هنوز ساخته نشده است</span>
                @endif
            </div>
        @endforeach
    </div>
@endif
@endsection
