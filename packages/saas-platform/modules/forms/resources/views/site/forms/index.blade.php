@extends('themes.default.layout')
@section('title', 'فرم‌های ارتباطی و درخواست')
@section('meta_description', 'فهرست فرم‌های فعال سایت برای ارسال درخواست، پیشنهاد و اطلاعات.')
@section('breadcrumb')
    <a href="{{ route('home') }}">خانه</a>
    <span>/</span>
    <span>فرم‌ها</span>
@endsection

@section('content')
<div class="module-forms-index container mx-auto py-8 px-4 max-w-3xl">
    <header class="mb-8">
        <h1 class="font-bold text-2xl md:text-3xl mb-2" style="color: var(--platform-heading-color, #111827);">فرم‌های سایت</h1>
        <p class="text-slate-600 text-sm md:text-base leading-relaxed">فرم مورد نظر را انتخاب کنید؛ پس از ارسال، پیام تأیید در همان صفحه نمایش داده می‌شود.</p>
    </header>

    @if($forms->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center text-slate-500">
            در حال حاضر فرم فعالی برای نمایش وجود ندارد.
        </div>
    @else
        <ul class="grid gap-4 sm:grid-cols-1 md:grid-cols-2">
            @foreach($forms as $form)
                <li>
                    <a href="{{ route('forms.show', $form->slug) }}" class="group flex flex-col h-full rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">
                        <span class="font-semibold text-lg text-gray-900 group-hover:text-indigo-700">{{ $form->title }}</span>
                        @if($form->intro_text)
                            <span class="mt-2 text-sm text-slate-600 line-clamp-2">{{ \Illuminate\Support\Str::limit(strip_tags($form->intro_text), 120) }}</span>
                        @endif
                        <span class="mt-4 inline-flex items-center text-sm font-medium text-indigo-600">
                            باز کردن فرم
                            <span class="mr-1 transition group-hover:translate-x-[-2px]" aria-hidden="true">←</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>

@push('json-ld')
<script type="application/ld+json">@json([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'فرم‌های سایت',
    'url' => route('forms.index'),
    'inLanguage' => 'fa-IR',
], JSON_UNESCAPED_UNICODE)</script>
@endpush
@endsection
