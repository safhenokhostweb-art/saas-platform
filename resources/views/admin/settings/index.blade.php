@extends('layouts.admin')

@section('header', 'تنظیمات فرم پروفایل')

@section('content')
<div class="space-y-6">
    <div class="card">
        <div class="card-body">
            <h2 class="text-lg font-bold mb-4">تنظیمات فرم پروفایل</h2>
            <p class="text-gray-600 mb-6">از این بخش می‌توانید نمایش فرم تکمیل پروفایل و اجبار تکمیل آن را مدیریت کنید.</p>

            <div class="space-y-6">
                {{-- نمایش فرم پروفایل --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <h3 class="font-semibold text-gray-900">نمایش فرم پروفایل</h3>
                        <p class="text-sm text-gray-600 mt-1">وقتی غیرفعال باشد، فرم تکمیل پروفایل به کاربران نمایش داده نمی‌شود.</p>
                    </div>
                    <form action="{{ route('admin.settings.profile-form.toggle-display') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-lg font-medium transition-colors {{ $formEnabled ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-gray-300 text-gray-700 hover:bg-gray-400' }}">
                            {{ $formEnabled ? 'فعال' : 'غیرفعال' }}
                        </button>
                    </form>
                </div>

                {{-- اجبار تکمیل پروفایل --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <h3 class="font-semibold text-gray-900">اجبار تکمیل پروفایل قبل از ورود</h3>
                        <p class="text-sm text-gray-600 mt-1">وقتی فعال باشد، مشتری حتماً باید فرم پروفایل را تکمیل کند و بعد بتواند وارد داشبورد شود.</p>
                    </div>
                    <form action="{{ route('admin.settings.profile-form.toggle-force') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-lg font-medium transition-colors {{ $forceComplete ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-gray-300 text-gray-700 hover:bg-gray-400' }}">
                            {{ $forceComplete ? 'فعال' : 'غیرفعال' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
