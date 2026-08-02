@extends(config('seo-advanced.layouts.admin'))

@section('title', 'Schema.org')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">Schema.org — سایت #{{ $siteId }}</h1>

    <h2 class="h5">Schema سطح سایت (تنظیمات فعلی)</h2>
    <pre class="bg-light p-3 border rounded" style="direction: ltr; text-align: left; white-space: pre-wrap;">{{ json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>

    <h2 class="h5 mt-4">ساخت Schema برای یک صفحه (Product / Article / FAQ / HowTo / Event / Review و…)</h2>
    <p class="text-muted small">نوع را انتخاب کنید و داده‌ها را به‌صورت JSON وارد کنید تا خروجی Schema.org معتبر تولید شود.</p>

    <form method="POST" action="{{ route('seo-advanced.admin.schema.build', $siteId) }}" class="row g-2 mb-3">
        @csrf
        <div class="col-md-3">
            <select name="type" class="form-select">
                @foreach ($supportedTypes as $t)
                    <option value="{{ $t }}" @selected(($selectedType ?? '') === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-7">
            <textarea name="payload" class="form-control" rows="3" placeholder='{"name": "...", "price": 100000}' style="direction: ltr;">{{ $payloadInput ?? '' }}</textarea>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100">ساخت</button></div>
    </form>

    @isset($error)
        @if ($error)
            <div class="alert alert-danger">{{ $error }}</div>
        @endif
    @endisset

    @isset($built)
        @if (!empty($built))
            <pre class="bg-light p-3 border rounded" style="direction: ltr; text-align: left; white-space: pre-wrap;">{{ json_encode($built, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        @endif
    @endisset
</div>
@endsection
