@extends('layouts.admin')

@section('title', 'اختصاص بسته موشن به قالب')
@section('header', 'اختصاص بسته موشن به قالب')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    @include('admin.motion-graphics.partials.site-nav', ['site' => $site, 'active' => 'templates'])

    @if($error)
        <div class="alert alert-warning">
            این قابلیت برای سایت #{{ $site->id }} فعال نیست ({{ $error->failureCode }}).
            برای فعال‌سازی: <code dir="ltr">php artisan motion:internal-test-site {{ $site->id }}</code>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="text-base font-bold text-gray-900 mb-4">قالب‌ها ({{ $templates->count() }})</h2>
                    @if($templates->isEmpty())
                        <p class="text-sm text-gray-400">هیچ قالبی یافت نشد.</p>
                    @else
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            @foreach($templates as $template)
                                <div class="border border-gray-200 rounded-lg p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <div class="text-sm font-semibold text-gray-800">{{ $template->name }}</div>
                                            <div class="text-xs text-gray-400">{{ $template->type }} · {{ $template->category }}</div>
                                        </div>
                                    </div>
                                    <details>
                                        <summary class="text-xs text-indigo-600 cursor-pointer">اختصاص بسته موشن به این قالب</summary>
                                        <form method="post" action="{{ route('admin.motion-graphics.templates.assign', [$site, $template]) }}" class="mt-3 space-y-2">
                                            @csrf
                                            @include('admin.motion-graphics.partials.assignment-fields')
                                            <div class="flex gap-2">
                                                <button type="submit" class="btn btn-sm btn-primary">اختصاص بسته به قالب</button>
                                                <button type="submit" formaction="{{ route('admin.motion-graphics.templates.activate', [$site, $template]) }}" class="btn btn-sm btn-success">فعال‌سازی برای این سایت</button>
                                            </div>
                                        </form>
                                    </details>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="text-base font-bold text-gray-900 mb-4">پیش‌نمایش سازگاری</h2>
                    <form method="post" action="{{ route('admin.motion-graphics.templates.preview', $site) }}" class="space-y-2">
                        @csrf
                        @include('admin.motion-graphics.partials.assignment-fields')
                        <button type="submit" class="btn btn-sm btn-secondary">بررسی سازگاری</button>
                    </form>

                    @if($preview !== null)
                        <div class="mt-4 space-y-2">
                            @forelse($preview as $item)
                                <div class="flex items-center justify-between text-sm border-b border-gray-100 py-2">
                                    <span class="font-mono text-xs text-gray-600">{{ $item->assetId }}</span>
                                    @if($item->result->valid)
                                        <span class="text-emerald-600 font-semibold">سازگار</span>
                                    @else
                                        <span class="text-red-600 font-semibold" title="{{ collect($item->result->errors)->pluck('message')->implode(' | ') }}">
                                            ناسازگار ({{ count($item->result->errors) }})
                                        </span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-400">هیچ دارایی برای بررسی وارد نشده است.</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
