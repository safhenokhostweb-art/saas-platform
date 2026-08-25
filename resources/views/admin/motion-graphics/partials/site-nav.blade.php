<div class="flex items-center justify-between flex-wrap gap-3">
    <nav class="flex items-center gap-2 text-sm">
        <a href="{{ route('admin.motion-graphics.dashboard', $site) }}" class="btn btn-sm {{ $active === 'dashboard' ? 'btn-primary' : 'btn-secondary' }}">داشبورد</a>
        <a href="{{ route('admin.motion-graphics.templates', $site) }}" class="btn btn-sm {{ $active === 'templates' ? 'btn-primary' : 'btn-secondary' }}">اختصاص قالب</a>
        <a href="{{ route('admin.motion-graphics.preview', $site) }}" class="btn btn-sm {{ $active === 'preview' ? 'btn-primary' : 'btn-secondary' }}">پیش‌نمایش اجرا</a>
        <a href="{{ route('admin.motion-graphics.bespoke.index', $site) }}" class="btn btn-sm {{ $active === 'bespoke' ? 'btn-primary' : 'btn-secondary' }}">موشن اختصاصی</a>
    </nav>
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <span>سایت آزمایشی: <strong class="text-gray-800">#{{ $site->id }} — {{ $site->title ?? $site->domain ?? 'بدون عنوان' }}</strong></span>
        <a href="{{ route('admin.motion-graphics.select-site') }}" class="btn btn-sm btn-outline-secondary">تغییر سایت</a>
    </div>
</div>
