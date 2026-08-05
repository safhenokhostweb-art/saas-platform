@extends(config('seo-advanced.layouts.admin'))

@section('title', 'دستیار هوشمند SEO')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">دستیار هوشمند SEO (AI SEO Assistant) — سایت #{{ $siteId }}</h1>

    <form method="POST" action="{{ route('seo-advanced.admin.ai-assistant.analyze', $siteId) }}" class="mb-4">
        @csrf
        <div class="mb-2">
            <label class="form-label">عنوان فعلی</label>
            <input type="text" name="current_title" class="form-control" value="{{ $current_title ?? '' }}">
        </div>
        <div class="mb-2">
            <label class="form-label">توضیحات فعلی</label>
            <textarea name="current_description" class="form-control">{{ $current_description ?? '' }}</textarea>
        </div>
        <div class="mb-2">
            <label class="form-label">محتوای صفحه</label>
            <textarea name="page_content" class="form-control" rows="6" required>{{ $page_content ?? '' }}</textarea>
        </div>
        <button class="btn btn-primary">تحلیل صفحه (Analyze Page)</button>
    </form>

    @if ($result)
        @if (!$result['available'])
            <div class="alert alert-warning">
                دستیار هوشمند SEO هنوز پیکربندی نشده است (Provider: {{ $result['provider'] }}).
                این بخش زیرساخت آماده است — با اتصال یک AI Provider واقعی فعال می‌شود.
            </div>
        @else
            <div class="card mb-3"><div class="card-body">
                <h2 class="h5">عنوان پیشنهادی</h2>
                <p>{{ $result['title_suggestion'] ?? '—' }}</p>
            </div></div>
            <div class="card mb-3"><div class="card-body">
                <h2 class="h5">توضیحات پیشنهادی</h2>
                <p>{{ $result['description_suggestion'] ?? '—' }}</p>
            </div></div>
            <div class="card mb-3"><div class="card-body">
                <h2 class="h5">پیشنهاد کلمات کلیدی</h2>
                <p>{{ implode('، ', $result['keyword_suggestions']) ?: '—' }}</p>
            </div></div>
            <div class="card mb-3"><div class="card-body">
                <h2 class="h5">پیشنهادات بهبود محتوا</h2>
                <ul class="mb-0">
                    @forelse ($result['content_improvements'] as $improvement)
                        <li>{{ $improvement }}</li>
                    @empty
                        <li class="text-muted">موردی یافت نشد.</li>
                    @endforelse
                </ul>
            </div></div>
        @endif
    @endif
</div>
@endsection
