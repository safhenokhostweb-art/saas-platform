@extends('layouts.admin')

@section('header', 'فرم‌های من')

@section('content')

<div class="max-w-4xl">
    @if($forms->isEmpty())
        <div class="bg-white rounded-2xl shadow p-6 text-center text-gray-500">
            @if(!$customer)
                <p>شما به هیچ مشتری یا سایتی تخصیص داده نشده‌اید. برای دسترسی به فرم‌ها با مدیر تماس بگیرید.</p>
            @else
                <p>فرمی برای سایت‌های شما تعریف نشده است.</p>
            @endif
        </div>
    @else
        <div class="bg-white rounded-2xl shadow overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h2 class="font-bold text-lg">لیست فرم‌های قابل دسترسی</h2>
                <p class="text-gray-500 text-sm mt-1">فرم‌های مرتبط با سایت‌های شما. روی هر لینک می‌توانید فرم را مشاهده یا پر کنید.</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($forms as $form)
                    @php
                        $sitesWithForm = $form->sites->filter(fn($s) => !isset($s->pivot->is_active) || $s->pivot->is_active);
                    @endphp
                    @if($sitesWithForm->isNotEmpty())
                        <li class="p-4 hover:bg-gray-50">
                            <div class="font-medium">{{ $form->title }}</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($sitesWithForm as $site)
                                    <a href="{{ route('site.forms.show_for_site', ['site' => $site, 'slug' => $form->slug]) }}" class="text-sm text-blue-600 hover:underline">
                                        {{ $site->title ?: $site->domain ?: 'سایت #' . $site->id }} →
                                    </a>
                                @endforeach
                            </div>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif
</div>

@endsection
