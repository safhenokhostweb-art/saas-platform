@extends(config('seo-advanced.layouts.admin'))

@section('title', 'انتخاب Property')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">انتخاب Property — سایت #{{ $siteId }}</h1>

    @if (empty($properties))
        <div class="alert alert-warning">هیچ Property ای در این حساب گوگل یافت نشد (یا اتصال معتبر نیست).</div>
    @else
        <ul class="list-group">
            @foreach ($properties as $property)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span style="direction: ltr;">{{ $property['url'] }} <small class="text-muted">({{ $property['permission_level'] }})</small></span>
                    <form method="POST" action="{{ route('seo-advanced.admin.search-console.select-property', $siteId) }}">
                        @csrf
                        <input type="hidden" name="property_url" value="{{ $property['url'] }}">
                        <button class="btn btn-sm btn-primary">انتخاب</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
