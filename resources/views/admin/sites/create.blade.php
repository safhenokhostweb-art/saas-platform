@extends('layouts.admin')

@section('header', 'عنوان صفحه')


@section('content')

<div class="container py-5">

    <h4 class="font-bold mb-4 text-xl">ساخت سایت جدید برای مشتری</h4>
    @if ($errors->any())
    <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
        <ul class="list-disc pr-5">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    <form method="POST" action="{{ route('admin.sites.store') }}" class="card p-4 shadow-sm rounded-xl border-0">
        @csrf

        <div class="mb-3">
            <label class="form-label">مشتری</label>
            <select name="customer_id" class="form-select" required>
                <option value="">انتخاب مشتری</option>
                @foreach($customers as $customer)
                <option value="{{ $customer->id }}">
                    {{ $customer->company_name ?? $customer->name ?? $customer->email ?? 'بدون نام' }}
                </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">عنوان سایت</label>
            <input type="text" name="title" class="form-control" placeholder="نام یا عنوان سایت" required>
        </div>

        <div class="mb-3">
            <label class="form-label">دامنه</label>
            <input type="text" name="domain" class="form-control" placeholder="مثال: example.com">
        </div>

        @if(isset($paymentGateways) && $paymentGateways->isNotEmpty())
        <div class="mb-3">
            <label class="form-label">درگاه پرداخت (اختیاری)</label>
            <select name="payment_gateway_id" class="form-select">
                <option value="">استفاده از درگاه پیش‌فرض پلتفرم</option>
                @foreach($paymentGateways as $gw)
                    <option value="{{ $gw->id }}">{{ $gw->name }} ({{ \App\Models\PaymentGateway::drivers()[$gw->driver] ?? $gw->driver }})</option>
                @endforeach
            </select>
        </div>
        @endif

        <hr>

        <h6 class="font-bold mt-3">ماژول‌ها</h6>
        @foreach($modules as $module)
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="modules[]" value="{{ $module->id }}">
            <label class="form-check-label">
                {{ $module->title }}
            </label>
        </div>
        @endforeach

        <hr>

        <h6 class="font-bold mt-3">اطلاعات هاست و دیتابیس</h6>

        <input name="hosting_company" class="form-control mb-2" placeholder="نام شرکت هاست">
        <input name="db_host" class="form-control mb-2" placeholder="آدرس سرور دیتابیس">
        <input name="db_name" class="form-control mb-2" placeholder="نام دیتابیس">
        <input name="db_user" class="form-control mb-2" placeholder="کاربر دیتابیس">
        <input name="db_password" class="form-control mb-2" placeholder="رمز عبور دیتابیس">

        <div class="row mt-3">
            <div class="col-md-4 mb-3">
                <label class="form-label">تاریخ انقضای هاست (شمسی)</label>
                <x-persian-date-input name="host_expires_at" value="" placeholder="۱۴۰۳/۰۷/۱۵" class="form-control" />
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">تاریخ انقضای دامنه (شمسی)</label>
                <x-persian-date-input name="domain_expires_at" value="" placeholder="۱۴۰۳/۰۷/۱۵" class="form-control" />
            </div>
        </div>

        <hr>

        <div class="mb-3">
            <label class="form-label">تاریخ انقضای پشتیبانی (شمسی)</label>
            <x-persian-date-input name="support_expires_at" value="" placeholder="۱۴۰۳/۰۷/۱۵" class="form-control" />
        </div>

        <div class="mb-3">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span>سایت فعال است</span>
            </label>
        </div>

        <button class="btn btn-primary w-full rounded-xl">
            ساخت سایت
        </button>
    </form>
</div>

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
@endpush
@push('scripts')
<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/persian-date/persian-date.min.js') }}"></script>
<script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.persian-date-input') && typeof jQuery !== 'undefined' && jQuery.fn.persianDatepicker) {
        jQuery('.persian-date-input').persianDatepicker({
            format: 'YYYY/MM/DD',
            persianDigit: true,
            initialValueType: 'persian'
        });
    }
});
</script>
@endpush
@endsection
