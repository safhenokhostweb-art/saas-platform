@extends('layouts.admin')

@section('title', 'دامنه و SSL سایت')
@section('header', 'دامنه و SSL: ' . $site->title)

@php
    use App\Models\SiteDomain;

    $badge = [
        SiteDomain::STATUS_AWAITING_DNS => 'bg-amber-100 text-amber-800 border border-amber-200',
        SiteDomain::STATUS_CONNECTED    => 'bg-sky-100 text-sky-800 border border-sky-200',
        SiteDomain::STATUS_ISSUING      => 'bg-indigo-100 text-indigo-800 border border-indigo-200',
        SiteDomain::STATUS_ACTIVE       => 'bg-emerald-600 text-white',
        SiteDomain::STATUS_FAILED       => 'bg-red-100 text-red-800 border border-red-200',
        SiteDomain::STATUS_REDIRECT     => 'bg-gray-100 text-gray-700 border border-gray-200',
    ];
    $busy = $primary->status === SiteDomain::STATUS_ISSUING || ($pending && $pending->status === SiteDomain::STATUS_ISSUING);
    $canProvision = $installation !== null;
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-5" dir="rtl" data-testid="site-domain-panel">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-500 max-w-3xl leading-7">
            وضعیت اتصال دامنهٔ سایت به سرور، گواهی SSL و جایگزینی دامنه با دامنهٔ اختصاصی مشتری.
            بررسی DNS فوری انجام می‌شود؛ صدور SSL و تغییر دامنه در پس‌زمینه اجرا می‌شوند و نتیجه در همین صفحه نمایش داده می‌شود.
        </p>
        <a href="{{ route('admin.sites.edit', $site) }}" class="btn btn-sm btn-outline-secondary">بازگشت به ویرایش سایت</a>
    </div>

    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @unless($canProvision)
        <div class="alert alert-warning" role="alert">{{ \App\Services\Domains\SiteDomainService::NO_INSTALLATION }}</div>
    @endunless

    {{-- Current domain --}}
    <section class="card" aria-labelledby="dom-current">
        <div class="card-body space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 id="dom-current" class="text-base font-bold text-gray-900 mb-1">دامنهٔ فعلی سایت</h2>
                    <a href="{{ ($primary->status === SiteDomain::STATUS_ACTIVE ? 'https://' : 'http://') . $primary->domain }}" target="_blank" rel="noopener" class="font-mono text-sm" dir="ltr">{{ $primary->domain }}</a>
                </div>
                <span class="text-xs font-semibold rounded-full px-3 py-1 {{ $badge[$primary->status] ?? '' }}" data-testid="primary-status">{{ $primary->statusLabel() }}</span>
            </div>

            <dl class="grid gap-3 sm:grid-cols-3 text-sm">
                <div><dt class="text-gray-500">IP سرور مقصد</dt><dd class="font-mono" dir="ltr">{{ $serverIp ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">انقضای گواهی</dt><dd>{{ $primary->ssl_expires_at ? date_fa($primary->ssl_expires_at) :($platformManaged && $primary->status === SiteDomain::STATUS_ACTIVE ? 'مدیریت‌شده در CDN آروان' : '—') }}</dd></div>
                <div><dt class="text-gray-500">www در گواهی</dt><dd>{{ $wwwFor($primary->domain) === null ? 'ندارد (زیردامنهٔ پلتفرم)' : ($primary->www_in_certificate ? 'بله' : 'خیر') }}</dd></div>
            </dl>

            @if($primary->last_error)
                <div class="alert alert-warning text-sm mb-0" role="status">{{ $primary->last_error }}</div>
            @endif

            @include('admin.sites.partials.domain-dns-records', ['row' => $primary])

            <div class="flex gap-2 flex-wrap">
                <form method="POST" action="{{ route('admin.sites.domain.check', [$site, $primary]) }}">
                    @csrf
                    <button class="btn btn-outline-primary" data-testid="check-dns">بررسی اتصال DNS</button>
                </form>
                <form method="POST" action="{{ route('admin.sites.domain.ssl', [$site, $primary]) }}">
                    @csrf
                    <button class="btn btn-primary" @disabled($busy || ! $canProvision) data-testid="issue-ssl">
                        {{ $primary->status === SiteDomain::STATUS_ACTIVE ? 'تمدید / صدور دوبارهٔ SSL' : 'اتصال و صدور SSL' }}
                    </button>
                </form>
            </div>
        </div>
    </section>

    @unless($platformManaged && ! $pending)
        @include('admin.sites.partials.domain-dns-guide', ['domain' => $pending?->domain ?? $primary->domain, 'serverIp' => $serverIp, 'withWww' => $wwwFor($pending?->domain ?? $primary->domain) !== null])
    @endunless

    {{-- Custom domain --}}
    <section class="card" aria-labelledby="dom-custom">
        <div class="card-body space-y-4">
            <h2 id="dom-custom" class="text-base font-bold text-gray-900 mb-0">دامنهٔ اختصاصی مشتری</h2>

            @if($pending)
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <span class="font-mono text-sm" dir="ltr">{{ $pending->domain }}</span>
                    <span class="text-xs font-semibold rounded-full px-3 py-1 {{ $badge[$pending->status] ?? '' }}" data-testid="pending-status">{{ $pending->statusLabel() }}</span>
                </div>
                @if($pending->last_error)
                    <div class="alert alert-warning text-sm mb-0" role="status">{{ $pending->last_error }}</div>
                @endif
                @include('admin.sites.partials.domain-dns-records', ['row' => $pending])
                <p class="text-xs text-gray-500 leading-6 mb-0">
                    با «اتصال و جایگزینی»، پس از تأیید DNS گواهی SSL دامنهٔ جدید صادر و سپس دامنهٔ سایت جایگزین می‌شود.
                    دامنهٔ فعلی تا پایان موفق کار بدون تغییر فعال می‌ماند و پس از آن با ریدایرکت 301 به دامنهٔ جدید هدایت می‌شود.
                </p>
                <div class="flex gap-2 flex-wrap">
                    <form method="POST" action="{{ route('admin.sites.domain.check', [$site, $pending]) }}">
                        @csrf
                        <button class="btn btn-outline-primary">بررسی اتصال DNS</button>
                    </form>
                    <form method="POST" action="{{ route('admin.sites.domain.activate', [$site, $pending]) }}" onsubmit="return confirm('دامنهٔ سایت با {{ $pending->domain }} جایگزین شود؟');">
                        @csrf
                        <button class="btn btn-success" @disabled($busy || ! $canProvision) data-testid="activate-domain">اتصال و جایگزینی دامنه</button>
                    </form>
                    <form method="POST" action="{{ route('admin.sites.domain.destroy', [$site, $pending]) }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger" @disabled($pending->status === SiteDomain::STATUS_ISSUING)>حذف</button>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('admin.sites.domain.store', $site) }}" class="space-y-2" data-testid="add-domain-form">
                    @csrf
                    <label for="dom-new" class="text-sm font-semibold text-gray-800 block">دامنهٔ ثبت‌شدهٔ مشتری (ایرنیک ‎.ir‎ یا بین‌المللی ‎.com/.net/.org‎)</label>
                    <div class="flex gap-2">
                        <input id="dom-new" name="domain" type="text" dir="ltr" class="form-control font-mono" placeholder="example.ir" autocomplete="off" spellcheck="false" value="{{ old('domain') }}" required @disabled(! $canProvision)>
                        <button class="btn btn-primary" @disabled(! $canProvision)>ثبت دامنه</button>
                    </div>
                    <p class="text-xs text-gray-500 mb-0">بدون http و www وارد کنید؛ نسخهٔ www به‌صورت خودکار پشتیبانی و به دامنهٔ اصلی هدایت می‌شود. ثبت دامنه، دامنهٔ فعلی را تغییر نمی‌دهد.</p>
                </form>
            @endif
        </div>
    </section>

    @if($aliases->isNotEmpty())
        <section class="card" aria-labelledby="dom-aliases">
            <div class="card-body">
                <h2 id="dom-aliases" class="text-base font-bold text-gray-900 mb-2">دامنه‌های قبلی (ریدایرکت 301 به دامنهٔ فعلی)</h2>
                <ul class="text-sm font-mono space-y-1 mb-0" dir="ltr">
                    @foreach($aliases as $alias)<li>{{ $alias->domain }}</li>@endforeach
                </ul>
            </div>
        </section>
    @endif
</div>

@if($busy)
    <script>setTimeout(function () { window.location.reload(); }, 20000);</script>
@endif
@endsection
