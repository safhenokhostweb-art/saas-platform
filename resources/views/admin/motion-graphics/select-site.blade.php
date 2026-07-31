@extends('layouts.admin')

@section('title', 'مدیریت موشن گرافیک')
@section('header', 'مدیریت موشن گرافیک')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="card">
        <div class="card-body">
            <h2 class="text-lg font-bold text-gray-900 mb-1">انتخاب سایت آزمایشی داخلی</h2>
            <p class="text-sm text-gray-500 mb-5">
                این بخش صرفاً برای تست داخلی ادمین است و هیچ مشتری به آن دسترسی ندارد.
                برای شروع، سایتی را که می‌خواهید به‌عنوان «سایت آزمایشی داخلی» با آن کار کنید انتخاب کنید.
                فعال‌سازی واقعی قابلیت برای هر سایت با دستور
                <code class="px-1 py-0.5 bg-gray-100 rounded text-xs" dir="ltr">php artisan motion:internal-test-site {id}</code>
                انجام می‌شود؛ اگر سایتی هنوز فعال نشده باشد، صفحه بعدی پیام «غیرفعال» نشان می‌دهد.
            </p>

            @if($sites->isEmpty())
                <p class="text-sm text-gray-400">هیچ سایتی یافت نشد.</p>
            @else
                <form method="get" action="" onsubmit="event.preventDefault(); window.location = this.dataset.base.replace('__ID__', this.site_id.value);" data-base="{{ url('admin/motion-graphics/__ID__/dashboard') }}">
                    <label class="form-label" for="site_id">سایت</label>
                    <select name="site_id" id="site_id" class="form-select mb-4">
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}">#{{ $site->id }} — {{ $site->title ?? $site->domain ?? 'بدون عنوان' }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary">ادامه</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
