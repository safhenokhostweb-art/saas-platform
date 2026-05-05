
@extends('layouts.app')

@section('content')
<div class="container mx-auto py-5 px-4 max-w-2xl">
    <h4 class="font-bold mb-4 text-xl">فرم‌های سایت: {{ $site->title }}</h4>

    <ul class="divide-y divide-gray-200 border border-gray-200 rounded-xl bg-white overflow-hidden">
        @forelse($forms as $form)
            <li class="flex justify-between items-center px-4 py-3 hover:bg-gray-50">
                <span>{{ $form->title }}</span>
                <a class="btn btn-primary text-sm px-3 py-1.5" href="{{ route('site.forms.show_for_site', [$site, $form->slug]) }}">
                    مشاهده فرم
                </a>
            </li>
        @empty
            <li class="px-4 py-4 text-gray-500 text-center">
                فرمی برای این سایت ثبت نشده است
            </li>
        @endforelse
    </ul>
</div>
@endsection
