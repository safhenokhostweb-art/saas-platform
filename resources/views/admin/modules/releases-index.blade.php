@extends('layouts.admin')

@section('title', 'تاریخچه نسخه ماژول‌ها | پنل مدیریت')
@section('header', 'تاریخچه نسخه ماژول‌ها')

@section('content')
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50 text-sm text-gray-700">
        لاگ تمام تغییرات نسخه ماژول‌ها در پلتفرم
    </div>
    <div class="divide-y">
        @forelse($releases as $release)
            <article class="p-4">
                <div class="flex items-center justify-between">
                    <div class="font-semibold text-gray-900">
                        {{ $release->module?->title ?? '—' }}
                        <span class="text-xs text-gray-500">({{ $release->module?->slug ?? '—' }})</span>
                    </div>
                    <div class="text-xs text-gray-500">{{ $release->distributed_at ? \App\Helpers\Jalali::formatFa($release->distributed_at, 'Y/m/d H:i') : '—' }}</div>
                </div>
                <div class="mt-1 text-sm text-gray-700">
                    v{{ $release->version }} <span class="text-xs text-gray-500">({{ $release->release_type }})</span>
                </div>
                @if($release->changelog)
                    <p class="mt-2 text-sm text-gray-700 whitespace-pre-line">{{ $release->changelog }}</p>
                @endif
                @if($release->module)
                    <div class="mt-2">
                        <a href="{{ route('admin.modules.releases', $release->module) }}" class="text-indigo-600 hover:underline text-xs">مدیریت نسخه‌های این ماژول</a>
                    </div>
                @endif
            </article>
        @empty
            <p class="p-6 text-sm text-gray-500">هنوز لاگ نسخه‌ای ثبت نشده است.</p>
        @endforelse
    </div>
    <div class="p-4 border-t">{{ $releases->links() }}</div>
</div>
@endsection
