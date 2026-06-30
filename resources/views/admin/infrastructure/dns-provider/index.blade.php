@extends('layouts.admin')

@section('title', 'تنظیمات DNS Provider')

@section('content')
<div class="container-fluid py-6" dir="rtl">

    {{-- Header --}}
    <div class="d-flex align-items-center gap-3 mb-6">
        <div class="bg-primary-subtle rounded-3 p-3">
            <x-core-ui::icon name="globe" class="text-primary" style="width:28px;height:28px" />
        </div>
        <div>
            <h1 class="h4 fw-bold mb-0">DNS Provider — آروان کلاد</h1>
            <p class="text-muted small mb-0">مدیریت رکوردهای DNS برای زیردامنه‌های مشتریان</p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <button type="button" id="btn-health-check" class="btn btn-outline-primary btn-sm">
                <x-core-ui::icon name="check-circle" style="width:16px;height:16px" />
                تست سلامت Token
            </button>
            <button type="button" id="btn-list-records" class="btn btn-outline-secondary btn-sm">
                <x-core-ui::icon name="list" style="width:16px;height:16px" />
                لیست رکوردها
            </button>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    <div class="row g-4">

        {{-- Left: Settings Form --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-semibold mb-0">پیکربندی DNS Provider</h6>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.infrastructure.dns-provider.save') }}" method="POST">
                        @csrf

                        <input type="hidden" name="provider" value="arvan">

                        <div class="mb-4">
                            <label class="form-label fw-semibold">دامنه ریشه <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="root_domain"
                                   class="form-control @error('root_domain') is-invalid @enderror"
                                   placeholder="مثال: safhenokhost.ir"
                                   value="{{ old('root_domain', $setting?->root_domain) }}">
                            <div class="form-text">زیردامنه‌های مشتریان زیر این دامنه ساخته می‌شوند.</div>
                            @error('root_domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">API Token آروان</label>
                            <input type="password"
                                   name="api_token"
                                   class="form-control @error('api_token') is-invalid @enderror"
                                   placeholder="{{ $setting?->api_token_encrypted ? '●●●●●●●● (تغییر ندهید اگر نمی‌خواهید عوض کنید)' : 'توکن را اینجا وارد کنید' }}"
                                   autocomplete="new-password">
                            <div class="form-text">
                                توکن به‌صورت رمزنگاری‌شده (AES-256-GCM) در پایگاه داده ذخیره می‌شود.
                                هرگز در .env یا کد ذخیره نمی‌شود.
                            </div>
                            @if($setting?->api_token_encrypted)
                            <div class="mt-2">
                                <span class="badge bg-success-subtle text-success">
                                    <x-core-ui::icon name="lock-closed" style="width:12px;height:12px" />
                                    توکن ذخیره‌شده (رمزنگاری‌شده)
                                </span>
                            </div>
                            @endif
                            @error('api_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">TTL پیش‌فرض (ثانیه)</label>
                                <input type="number"
                                       name="default_ttl"
                                       class="form-control"
                                       min="60" max="86400"
                                       value="{{ old('default_ttl', $setting?->default_ttl ?? 120) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">آدرس Base URL</label>
                                <input type="text"
                                       name="api_base_url"
                                       class="form-control"
                                       placeholder="https://napi.arvancloud.ir/cdn/4.0/domains"
                                       value="{{ old('api_base_url', $setting?->api_base_url) }}">
                                <div class="form-text">خالی بگذارید برای استفاده از پیش‌فرض آروان.</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-5">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="cloud_proxy"
                                           id="cloud_proxy"
                                           value="1"
                                           {{ old('cloud_proxy', $setting?->cloud_proxy) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="cloud_proxy">
                                        پروکسی CDN آروان روی رکوردها
                                    </label>
                                </div>
                                <div class="form-text">فعال = رکوردها از طریق CDN آروان سرو می‌شوند.</div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="is_active"
                                           id="is_active"
                                           value="1"
                                           {{ old('is_active', $setting?->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        DNS Provider فعال است
                                    </label>
                                </div>
                                <div class="form-text">غیرفعال = مرحله DNS در pipeline نصب رد می‌شود.</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <x-core-ui::icon name="check" style="width:16px;height:16px" />
                            ذخیره تنظیمات
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Right: Status Cards --}}
        <div class="col-lg-5">

            {{-- Health Status --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-semibold mb-0">وضعیت سلامت</h6>
                </div>
                <div class="card-body p-4">
                    @if($setting)
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="badge {{ $setting->healthBadge() }} fs-6 px-3 py-2">
                            {{ match($setting->health_status) {
                                'ok'    => '✓ سالم',
                                'error' => '✗ خطا',
                                default => '؟ بررسی نشده'
                            } }}
                        </span>
                        @if($setting->health_checked_at)
                        <small class="text-muted">آخرین بررسی: @datetimeFa($setting->health_checked_at)</small>
                        @endif
                    </div>
                    @if($setting->health_message)
                    <p class="text-muted small mb-0 font-monospace">{{ $setting->health_message }}</p>
                    @endif
                    @else
                    <p class="text-muted">هنوز تنظیم نشده است.</p>
                    @endif

                    <div id="health-result" class="mt-3" style="display:none"></div>
                </div>
            </div>

            {{-- Pipeline Integration Info --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-semibold mb-0">یکپارچگی با Pipeline نصب</h6>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex gap-2 align-items-start mb-3">
                        <span class="badge bg-primary-subtle text-primary mt-1">DNS</span>
                        <div>
                            <div class="fw-semibold small">مرحله CONFIGURE_DNS</div>
                            <div class="text-muted small">قبل از پیکربندی وب‌سرور اجرا می‌شود</div>
                        </div>
                    </div>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-1">✓ رکورد A برای زیردامنه ایجاد می‌شود</li>
                        <li class="mb-1">✓ انتظار برای انتشار DNS (حداکثر ۵ دقیقه)</li>
                        <li class="mb-1">✓ در صورت شکست، Rollback خودکار رکورد حذف می‌شود</li>
                        <li class="mb-1">✓ اگر provider غیرفعال باشد، مرحله رد می‌شود</li>
                        <li class="mb-1">✓ دامنه‌های غیرزیردامنه، رد می‌شوند</li>
                        <li>✓ تمام عملیات در <code>customer_installation_logs</code> ثبت می‌شود</li>
                    </ul>
                </div>
            </div>

            {{-- DNS Records Viewer --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-semibold mb-0">رکوردهای DNS موجود</h6>
                </div>
                <div class="card-body p-4">
                    <div id="records-loading" style="display:none">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="ms-2 small">در حال دریافت رکوردها...</span>
                    </div>
                    <div id="records-result">
                        <p class="text-muted small">برای مشاهده رکوردها روی «لیست رکوردها» کلیک کنید.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Health Check
    document.getElementById('btn-health-check').addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'در حال بررسی...';

        fetch('{{ route('admin.infrastructure.dns-provider.health') }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
        })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('health-result');
            el.style.display = '';
            el.className = 'alert ' + (data.ok ? 'alert-success' : 'alert-danger') + ' mt-3 small';
            el.textContent = data.message;
        })
        .catch(() => {
            const el = document.getElementById('health-result');
            el.style.display = '';
            el.className = 'alert alert-danger mt-3 small';
            el.textContent = 'خطا در ارتباط با سرور.';
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'تست سلامت Token';
        });
    });

    // List Records
    document.getElementById('btn-list-records').addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        document.getElementById('records-loading').style.display = '';
        document.getElementById('records-result').innerHTML = '';

        fetch('{{ route('admin.infrastructure.dns-provider.records') }}', {
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('records-loading').style.display = 'none';
            const el = document.getElementById('records-result');
            if (!data.ok || !data.records.length) {
                el.innerHTML = '<p class="text-muted small">' + (data.message || 'رکوردی یافت نشد.') + '</p>';
                return;
            }
            let html = '<div class="table-responsive"><table class="table table-sm table-bordered small">'
                + '<thead><tr><th>نوع</th><th>نام</th><th>مقدار</th><th>TTL</th></tr></thead><tbody>';
            data.records.forEach(r => {
                let vals = '';
                if (Array.isArray(r.value)) {
                    vals = r.value.map(v => v.ip || v.text || v.host || JSON.stringify(v)).join(', ');
                } else if (r.value && typeof r.value === 'object') {
                    vals = r.value.ip || r.value.text || r.value.host || JSON.stringify(r.value);
                } else {
                    vals = String(r.value || '');
                }
                html += `<tr><td>${r.type.toUpperCase()}</td><td>${r.name}</td><td>${vals}</td><td>${r.ttl}</td></tr>`;
            });
            html += '</tbody></table></div>';
            el.innerHTML = html;
        })
        .catch(() => {
            document.getElementById('records-loading').style.display = 'none';
            document.getElementById('records-result').innerHTML = '<p class="text-danger small">خطا در دریافت رکوردها.</p>';
        })
        .finally(() => { btn.disabled = false; });
    });
});
</script>
@endpush
@endsection
