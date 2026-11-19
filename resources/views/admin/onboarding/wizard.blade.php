@extends('layouts.admin')
@section('title', 'راه‌اندازی مشتری و سایت')
@section('header', 'راه‌اندازی مشتری و سایت')

@php
    $stepFields = [
        1 => ['mobile', 'customer_name', 'company_name'],
        2 => ['site_title', 'domain_mode', 'domain'],
        3 => ['license_type', 'business_vertical', 'kit_module_ids', 'kit_module_ids.*', 'active_module_ids', 'active_module_ids.*', 'base_modules', 'base_modules.*'],
        4 => ['theme_id'],
        5 => ['remote_server_id', 'ssl_enabled', 'backup_policy', 'web_root_path', 'db_name', 'db_user', 'db_host', 'db_password', 'resource_plan', 'ram_mb', 'disk_gb', 'soft_disk_gb', 'cpu_cores', 'bandwidth_gb'],
    ];
    $errorStep = null;
    foreach ($stepFields as $n => $fields) {
        foreach ($fields as $f) {
            if ($errors->has($f)) { $errorStep = $errorStep ?? $n; }
        }
    }
    $initial = [
        'mobile'            => old('mobile', $prefill['mobile'] ?? ''),
        'customer_name'     => old('customer_name', ''),
        'company_name'      => old('company_name', ''),
        'site_title'        => old('site_title', ''),
        'domain_mode'       => old('domain_mode', $options['root_domain'] !== '' ? 'subdomain' : 'custom'),
        'domain'            => old('domain', ''),
        'license_type'      => old('license_type', 'basic'),
        'business_vertical' => old('business_vertical', ''),
        'kit_module_ids'    => array_map('intval', (array) old('kit_module_ids', [])),
        'active_module_ids' => array_map('intval', (array) old('active_module_ids', [])),
        'base_modules'      => (array) old('base_modules', ['forms']),
        'theme_id'          => old('theme_id') !== null && old('theme_id') !== '' ? (int) old('theme_id') : null,
        'remote_server_id'  => old('remote_server_id') ? (int) old('remote_server_id') : (collect($options['servers'])->firstWhere('implemented', true)['id'] ?? null),
        'ssl_enabled'       => (bool) old('ssl_enabled', true),
        'backup_policy'     => old('backup_policy', 'daily'),
        // Infrastructure & DB fields
        'web_root_path'     => old('web_root_path', ''),
        'db_name'           => old('db_name', ''),
        'db_user'           => old('db_user', ''),
        'db_host'           => old('db_host', '127.0.0.1'),
        'db_password'       => old('db_password', ''),
        // Resource Governance fields
        'resource_plan'     => old('resource_plan', 'basic'),
        'ram_mb'            => (int) old('ram_mb', 1024),
        'disk_gb'           => (float) old('disk_gb', 5.0),
        'soft_disk_gb'      => (float) old('soft_disk_gb', 3.5),
        'cpu_cores'         => (float) old('cpu_cores', 1.0),
        'bandwidth_gb'      => (float) old('bandwidth_gb', 10.0),
    ];
@endphp

@push('head')
<style>
.ob{--ob-accent:var(--admin-primary,#4f46e5);--ob-accent-hover:var(--admin-primary-hover,#4338ca);--ob-accent-soft:var(--ui-indigo-light,#eef2ff);--ob-surface:var(--admin-surface,#fff);--ob-soft:var(--admin-surface-soft,#f8fafc);--ob-border:var(--admin-border,#e2e8f0);--ob-text:var(--ui-text,#0f172a);--ob-muted:var(--ui-text-secondary,#475569);--ob-ok:var(--ui-success,#047857);--ob-ok-soft:var(--ui-success-light,#ecfdf5);--ob-err:var(--ui-danger,#b91c1c);--ob-err-soft:var(--ui-danger-light,#fef2f2);--ob-warn:#92400e;--ob-warn-soft:#fffbeb;--ob-radius:var(--admin-radius,14px);
    max-inline-size:60rem;margin-inline:auto;padding-block:1rem 4rem;color:var(--ob-text)}
.ob-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:.75rem;margin-block-end:1.25rem}
.ob-head h1{font-size:1.35rem;font-weight:800;margin:0}
.ob-head p{margin:.25rem 0 0;color:var(--ob-muted);font-size:.875rem;line-height:1.9}
.ob-steps{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.375rem;list-style:none;margin:0 0 1.25rem;padding:.375rem;background:var(--ob-surface);border:1px solid var(--ob-border);border-radius:var(--ob-radius)}
.ob-step{display:flex;flex-direction:column;align-items:center;gap:.25rem;padding:.5rem .25rem;border-radius:calc(var(--ob-radius) - 4px);font-size:.75rem;color:var(--ob-muted);background:transparent;border:0;cursor:default;inline-size:100%;font:inherit}
.ob-step[data-state=done]{color:var(--ob-ok);cursor:pointer}
.ob-step[data-state=done]:hover{background:var(--ob-ok-soft)}
.ob-step[data-state=current]{background:var(--ob-accent);color:#fff;font-weight:700}
.ob-step-num{display:grid;place-items:center;inline-size:1.75rem;block-size:1.75rem;border-radius:999px;border:2px solid currentColor;font-weight:700;font-size:.8rem}
.ob-step[data-state=current] .ob-step-num{background:#fff;color:var(--ob-accent);border-color:#fff}
.ob-step-label{display:none}
@media (min-width:640px){.ob-step-label{display:block}}
.ob-card{background:var(--ob-surface);border:1px solid var(--ob-border);border-radius:var(--ob-radius);box-shadow:var(--admin-shadow,0 8px 24px rgba(15,23,42,.06));padding:1.25rem}
@media (min-width:640px){.ob-card{padding:1.75rem}}
.ob-card h2{font-size:1.05rem;font-weight:800;margin:0 0 .25rem}
.ob-card .ob-lead{color:var(--ob-muted);font-size:.85rem;margin:0 0 1.25rem;line-height:1.9}
.ob-grid{display:grid;gap:1rem}
@media (min-width:640px){.ob-grid-2{grid-template-columns:1fr 1fr}}
.ob-field label{display:block;font-size:.85rem;font-weight:600;margin-block-end:.4rem;color:var(--ob-text)}
.ob-req{color:var(--ob-err)}
.ob-input{inline-size:100%;min-block-size:2.75rem;padding:.55rem .85rem;border:1px solid var(--ob-border);border-radius:10px;background:var(--ob-surface);color:var(--ob-text);font:inherit;font-size:.95rem;transition:border-color .15s,box-shadow .15s}
.ob-input:focus{outline:none;border-color:var(--ob-accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--ob-accent) 22%,transparent)}
.ob-input[aria-invalid=true]{border-color:var(--ob-err)}
.ob-ltr{direction:ltr;text-align:left}
.ob-hint{font-size:.78rem;color:var(--ob-muted);margin-block-start:.35rem;line-height:1.8}
.ob-err{font-size:.8rem;color:var(--ob-err);margin-block-start:.35rem}
.ob-ok-text{font-size:.8rem;color:var(--ob-ok);margin-block-start:.35rem}
.ob-affix{display:flex;align-items:stretch;direction:ltr}
.ob-affix .ob-input{border-start-end-radius:0;border-end-end-radius:0}
.ob-affix span{display:flex;align-items:center;padding-inline:.75rem;border:1px solid var(--ob-border);border-inline-start:0;border-start-end-radius:10px;border-end-end-radius:10px;background:var(--ob-soft);color:var(--ob-muted);font-size:.9rem;white-space:nowrap}
.ob-seg{display:inline-flex;flex-wrap:wrap;gap:.25rem;padding:.25rem;border-radius:12px;background:var(--ob-soft);border:1px solid var(--ob-border)}
.ob-seg button{border:0;background:transparent;padding:.5rem .9rem;border-radius:9px;font:inherit;font-size:.85rem;color:var(--ob-muted);cursor:pointer;min-block-size:2.5rem}
.ob-seg button[aria-pressed=true]{background:var(--ob-surface);color:var(--ob-accent);font-weight:700;box-shadow:0 1px 3px rgba(15,23,42,.12)}
.ob-seg button:disabled{opacity:.45;cursor:not-allowed}
.ob-choices{display:grid;gap:.625rem;grid-template-columns:repeat(auto-fill,minmax(min(100%,13rem),1fr))}
.ob-choice{position:relative;display:flex;flex-direction:column;gap:.25rem;padding:.85rem 1rem;border:1.5px solid var(--ob-border);border-radius:12px;background:var(--ob-surface);cursor:pointer;text-align:start;font:inherit;color:inherit;transition:border-color .15s,background .15s;min-block-size:3rem}
.ob-choice:hover{border-color:color-mix(in srgb,var(--ob-accent) 45%,var(--ob-border))}
.ob-choice[aria-checked=true],.ob-choice[aria-pressed=true]{border-color:var(--ob-accent);background:var(--ob-accent-soft)}
.ob-choice:disabled,.ob-choice[aria-disabled=true]{opacity:.5;cursor:not-allowed}
.ob-choice strong{font-size:.9rem}
.ob-choice small{font-size:.75rem;color:var(--ob-muted);line-height:1.7}
.ob-choice:focus-visible,.ob-step:focus-visible,.ob-seg button:focus-visible,.ob-btn:focus-visible{outline:2px solid var(--ob-accent);outline-offset:2px}
.ob-badge{display:inline-flex;align-items:center;gap:.25rem;padding:.1rem .55rem;border-radius:999px;font-size:.7rem;font-weight:700;background:var(--ob-soft);color:var(--ob-muted);border:1px solid var(--ob-border)}
.ob-badge-accent{background:var(--ob-accent-soft);color:var(--ob-accent);border-color:transparent}
.ob-badge-ok{background:var(--ob-ok-soft);color:var(--ob-ok);border-color:transparent}
.ob-theme-img{aspect-ratio:16/9;inline-size:100%;border-radius:8px;object-fit:cover;background:linear-gradient(135deg,var(--ob-soft),var(--ob-accent-soft));margin-block-end:.4rem}
.ob-panel{display:flex;gap:.75rem;align-items:flex-start;padding:.85rem 1rem;border-radius:12px;font-size:.85rem;line-height:1.9}
.ob-panel-info{background:var(--ob-accent-soft);color:var(--ob-text)}
.ob-panel-ok{background:var(--ob-ok-soft);color:var(--ob-text)}
.ob-panel-warn{background:var(--ob-warn-soft);color:var(--ob-warn)}
.ob-panel-err{background:var(--ob-err-soft);color:var(--ob-err)}
.ob-sites{display:flex;flex-wrap:wrap;gap:.375rem;margin-block-start:.5rem}
.ob-nav{position:sticky;inset-block-end:0;display:flex;justify-content:space-between;gap:.75rem;margin-block-start:1rem;padding:.75rem;background:color-mix(in srgb,var(--admin-bg,#f1f5f9) 85%,transparent);backdrop-filter:blur(6px);border-radius:var(--ob-radius)}
.ob-btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;min-block-size:2.75rem;padding:.6rem 1.25rem;border-radius:10px;font:inherit;font-weight:700;font-size:.9rem;cursor:pointer;border:1px solid transparent;transition:background .15s,opacity .15s}
.ob-btn-primary{background:var(--ob-accent);color:#fff}
.ob-btn-primary:hover{background:var(--ob-accent-hover)}
.ob-btn-ghost{background:var(--ob-surface);color:var(--ob-text);border-color:var(--ob-border)}
.ob-btn:disabled{opacity:.55;cursor:not-allowed}
.ob-summary{display:grid;gap:.75rem}
.ob-summary section{border:1px solid var(--ob-border);border-radius:12px;padding:.85rem 1rem}
.ob-summary header{display:flex;justify-content:space-between;align-items:center;margin-block-end:.4rem}
.ob-summary h3{font-size:.85rem;font-weight:800;margin:0}
.ob-summary dl{display:grid;grid-template-columns:auto 1fr;gap:.3rem 1rem;margin:0;font-size:.85rem}
.ob-summary dt{color:var(--ob-muted)}
.ob-summary dd{margin:0;font-weight:600;overflow-wrap:anywhere}
.ob-link{background:none;border:0;padding:0;color:var(--ob-accent);font:inherit;font-size:.8rem;font-weight:700;cursor:pointer}
.ob-pipeline{list-style:none;margin:0;padding:0;display:grid;gap:.4rem;font-size:.82rem;color:var(--ob-muted)}
.ob-pipeline li{display:flex;gap:.5rem;align-items:flex-start}
.ob-pipeline li::before{content:"";flex:none;inline-size:.5rem;block-size:.5rem;margin-block-start:.55rem;border-radius:999px;background:var(--ob-accent)}
.ob-spin{inline-size:1rem;block-size:1rem;border:2px solid currentColor;border-inline-end-color:transparent;border-radius:999px;animation:ob-spin .7s linear infinite}
@keyframes ob-spin{to{transform:rotate(360deg)}}
@media (prefers-reduced-motion:reduce){.ob *{transition:none!important;animation:none!important}}
[x-cloak]{display:none!important}
</style>
@endpush

@section('content')
<div class="ob" dir="rtl" x-data="onboardingWizard(@js($options), @js($initial), @js($prefill), {{ $errorStep ?? 'null' }})" x-init="init()">

    <div class="ob-head">
        <div>
            <p>یک مسیر برای همه‌چیز: مشتری، دامنه، لایسنس و کیت‌ها، قالب و استقرار روی سرور، و مدیریت به‌روزرسانی‌های ناوگان سایت‌ها.</p>
        </div>
        <a href="{{ route('admin.infrastructure.customer-deployment.history') }}" class="ob-btn ob-btn-ghost">تاریخچهٔ راه‌اندازی‌ها</a>
    </div>

    {{-- ── Main Mode Switcher: Onboarding vs Fleet Updates ── --}}
    <div style="display:flex;gap:.5rem;margin-bottom:1.25rem;border-bottom:2px solid var(--ob-border);padding-bottom:.75rem">
        <a href="{{ route('admin.onboarding.create', ['tab' => 'onboarding']) }}"
           class="ob-btn {{ ($activeTab ?? 'onboarding') === 'onboarding' ? 'ob-btn-primary' : 'ob-btn-ghost' }}"
           style="border-radius:999px;font-weight:700">
            ➕ راه‌اندازی سایت جدید (Onboarding)
        </a>
        <a href="{{ route('admin.onboarding.create', ['tab' => 'fleet-updates']) }}"
           class="ob-btn {{ ($activeTab ?? 'onboarding') === 'fleet-updates' ? 'ob-btn-primary' : 'ob-btn-ghost' }}"
           style="border-radius:999px;font-weight:700;display:inline-flex;align-items:center;gap:.5rem">
            🔄 مدیریت به‌روزرسانی‌های ناوگان (Fleet Updates)
            @if(!empty($fleetUpdates['summary']['total_pending']))
                <span style="background:#ef4444;color:#fff;border-radius:999px;padding:.15rem .5rem;font-size:.75rem;font-weight:800">
                    {{ $fleetUpdates['summary']['total_pending'] }}
                </span>
            @endif
        </a>
    </div>

    {{-- ── Persistent Announcement Banner: Available Updates ── --}}
    @if(!empty($fleetUpdates['summary']['total_pending']))
        <div class="ob-panel ob-panel-ok" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;margin-bottom:1.25rem;border-radius:var(--ob-radius);padding:1rem 1.25rem">
            <div style="display:flex;align-items:flex-start;gap:.75rem">
                <span style="font-size:1.4rem">🔔</span>
                <div style="flex:1">
                    <strong style="font-size:.95rem">اعلان‌های به‌روزرسانی پلتفرم:</strong>
                    <ul style="margin:.35rem 0 0;padding-inline-start:1.2rem;font-size:.85rem;line-height:1.8">
                        @if(!empty($fleetUpdates['summary']['themes_count']))
                            <li><strong>آپدیت قالب‌ها:</strong> {{ $fleetUpdates['summary']['themes_count'] }} سایت دارای نسخه جدیدتر قالب و ویجت‌های کانواس هستند.</li>
                        @endif
                        @if(!empty($fleetUpdates['summary']['packages_count']))
                            <li><strong>آپدیت هسته و ویجت‌ها:</strong> نسخه جدید پکیج هسته و توسعه موتور کانواس در دسترس است ({{ $fleetUpdates['summary']['packages_count'] }} سایت نیازمند به‌روزرسانی).</li>
                        @endif
                        @if(!empty($fleetUpdates['summary']['motions_count']))
                            <li><strong>آپدیت موشن‌گرافیک‌ها:</strong> {{ $fleetUpdates['summary']['motions_count'] }} موشن‌گرافیک دارای لایسنس آماده ارتقا هستند.</li>
                        @endif
                    </ul>
                </div>
                @if(($activeTab ?? 'onboarding') !== 'fleet-updates')
                    <a href="{{ route('admin.onboarding.create', ['tab' => 'fleet-updates']) }}" class="ob-btn ob-btn-primary" style="font-size:.8rem;padding:.4rem .75rem">
                        مشاهده و اعمال در ناوگان ←
                    </a>
                @endif
            </div>
        </div>
    @endif

    @if(($activeTab ?? 'onboarding') === 'fleet-updates')
        {{-- ── Fleet Updates Management Tab ── --}}
        <section class="ob-card" style="margin-bottom:2rem">
            <h2>مدیریت، نسخه‎بندی و به‌روزرسانی‌های ناوگان (Fleet Management & Rollback)</h2>
            <p class="ob-lead">
                تمامی عملیات به‌روزرسانی مستقیماً از طریق پایپلاین امن پلتفرم و بدون کوچک‌ترین دخالت دستی یا SSH انجام می‌شوند.
                موتور هوشمند تطبیق ۳-جانبه (3-Way Merge) تضمین می‌کند که <strong>۱۰۰٪ محتوا و صفحات ویرایش‌شده توسط مشتری دست‌نخورده باقی بمانند</strong> و هیچ بلاک ناخواسته‌ای به صفحات تغییریافته تزریق نشود.
            </p>

            {{-- ── Architectural Deployment Boundaries Notice ── --}}
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:var(--ob-radius);padding:1rem;margin:1rem 0;font-size:.85rem;color:#1e40af">
                <div style="display:flex;align-items:center;gap:.5rem;font-weight:700;margin-bottom:.35rem">
                    <span>🛡️ قوانین قطعی استقرار مخازن (Deployment Boundaries):</span>
                </div>
                <ul style="margin:0;padding-inline-start:1.25rem;line-height:1.6">
                    <li><strong>پروژه saas-platform:</strong> منحصراً از طریق <strong>Deployment Hub</strong> مستقیم روی سرور پلتفرم دیپلوی می‌شود و <strong>هرگز</strong> به عنوان آیتم آپدیت ناوگان مشتریان عرضه نمی‌گردد.</li>
                    <li><strong>پروژه customer-site (هسته، داشبورد، کانواس و ویجت‌ها):</strong> به صورت خودکار در Deployment Hub بیلد و دیپلوی شده و به صورت پکیج آماده (<code>CustomerInstallationPackage</code>) در این فرم ظاهر می‌گردد تا <strong>تنها پس از تأیید ادمین پلتفرم</strong> روی سایت‌های هدف اعمال شود.</li>
                </ul>
            </div>

            {{-- ── Component Versioning Cards (SemVer & Changelogs) ── --}}
            <h3 style="font-size:.95rem;font-weight:700;margin:1.75rem 0 .75rem">نسخه‌بندی جامع اجزا (Component Versioning & Changelogs):</h3>
            @if(!empty($fleetUpdates['components']))
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:1rem;margin-bottom:1.5rem">
                    @foreach($fleetUpdates['components'] as $key => $comp)
                        <div style="background:#ffffff;border:1px solid var(--ob-border);border-radius:var(--ob-radius);padding:1rem;box-shadow:0 1px 3px rgba(0,0,0,.03)">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                                <span style="font-weight:700;font-size:.85rem;color:var(--ob-text)">{{ $comp['name'] }}</span>
                                <span style="background:#ecfdf5;color:#065f46;font-family:monospace;font-weight:700;font-size:.8rem;padding:.2rem .45rem;border-radius:4px">v{{ $comp['version'] }}</span>
                            </div>
                            <p style="font-size:.75rem;color:var(--ob-muted);margin:0 0 .5rem;line-height:1.4">{{ $comp['summary'] }}</p>
                            <div style="font-size:.7rem;color:#475569;border-top:1px dashed var(--ob-border);padding-top:.5rem">
                                <strong>تغییرات کلیدی (Changelog):</strong>
                                <ul style="margin:.25rem 0 0;padding-inline-start:1rem;line-height:1.35">
                                    @foreach(array_slice($comp['changelog'], 0, 2) as $logItem)
                                        <li>{{ $logItem }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- 1. Theme Updates Table --}}
            <h3 style="font-size:.95rem;font-weight:700;margin:1.5rem 0 .5rem">۱. قالب‌ها و ویجت‌های نیازمند به‌روزرسانی:</h3>
            @if(empty($fleetUpdates['themes']))
                <div style="background:#f8fafc;border:1px dashed var(--ob-border);border-radius:var(--ob-radius);padding:1.5rem;text-align:center;color:var(--ob-muted);font-size:.85rem">
                    ✅ تمامی سایت‌های فعال آخرین نسخهٔ رسمی قالب و ویجت‌های خود را دارند و هیچ آپدیت معوقه‌ای وجود ندارد.
                </div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:.85rem;text-align:right">
                        <thead>
                            <tr style="border-bottom:2px solid var(--ob-border);background:#f8fafc">
                                <th style="padding:.65rem">دامنه و سایت</th>
                                <th style="padding:.65rem">قالب فعال</th>
                                <th style="padding:.65rem">مدل عرضه</th>
                                <th style="padding:.65rem">نسخه فعلی</th>
                                <th style="padding:.65rem">نسخه در دسترس</th>
                                <th style="padding:.65rem">وضعیت گارد انطباق</th>
                                <th style="padding:.65rem;text-align:center">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fleetUpdates['themes'] as $item)
                                <tr style="border-bottom:1px solid var(--ob-border)">
                                    <td style="padding:.75rem">
                                        <strong>{{ $item['site_title'] }}</strong>
                                        <div style="font-size:.75rem;color:var(--ob-muted);direction:ltr;text-align:right">{{ $item['domain'] }}</div>
                                    </td>
                                    <td style="padding:.75rem">
                                        <span class="ob-badge ob-badge-outline">{{ $item['theme_name'] }}</span>
                                    </td>
                                    <td style="padding:.75rem">
                                        @if($item['distribution_model'] === 'private_license')
                                            <span style="background:#fef3c7;color:#92400e;padding:.2rem .45rem;border-radius:4px;font-size:.75rem;font-weight:600">لایسنس اختصاصی</span>
                                        @elseif($item['distribution_model'] === 'free')
                                            <span style="background:#f3f4f6;color:#4b5563;padding:.2rem .45rem;border-radius:4px;font-size:.75rem;font-weight:600">رایگان</span>
                                        @else
                                            <span style="background:#ecfdf5;color:#065f46;padding:.2rem .45rem;border-radius:4px;font-size:.75rem;font-weight:600">مارکت‌پلیس</span>
                                        @endif
                                    </td>
                                    <td style="padding:.75rem">
                                        <span style="color:#ef4444;font-family:monospace;font-weight:700">v{{ $item['installed_version'] }}</span>
                                    </td>
                                    <td style="padding:.75rem">
                                        <span style="color:#059669;font-family:monospace;font-weight:700">v{{ $item['available_version'] }}</span>
                                    </td>
                                    <td style="padding:.75rem">
                                        @if($item['eligible'])
                                            <span style="color:#059669;font-weight:700;display:inline-flex;align-items:center;gap:.25rem">
                                                ✅ واجد شرایط
                                            </span>
                                        @else
                                            <span style="color:#dc2626;font-weight:700;display:inline-flex;align-items:center;gap:.25rem" title="{{ $item['eligibility_reason'] }}">
                                                ⛔ {{ $item['eligibility_reason'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td style="padding:.75rem;text-align:center">
                                        @if($item['eligible'])
                                            <form method="POST" action="{{ route('admin.onboarding.fleet-updates.apply') }}" onsubmit="return confirm('آیا از اعمال به‌روزرسانی غیرمخرب به نسخه {{ $item['available_version'] }} اطمینان دارید؟ تمام صفحات مشتری محفوظ خواهد ماند.');">
                                                @csrf
                                                <input type="hidden" name="site_id" value="{{ $item['site_id'] }}">
                                                <input type="hidden" name="release_id" value="{{ $item['available_release_id'] }}">
                                                <button type="submit" class="ob-btn ob-btn-primary" style="font-size:.75rem;padding:.35rem .75rem">
                                                    اعمال آپدیت امن
                                                </button>
                                            </form>
                                        @else
                                            <button type="button" disabled class="ob-btn ob-btn-ghost" style="font-size:.75rem;padding:.35rem .75rem;opacity:.5;cursor:not-allowed">
                                                غیرقابل اعمال
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- 2. Core Package Updates Table --}}
            <h3 style="font-size:.95rem;font-weight:700;margin:2rem 0 .5rem">۲. پکیج‌های هسته، ویجت‌ها و موتور کانواس (Customer Installation Packages):</h3>
            
            {{-- P0 safety (2026-11-19): package upgrade is disabled until it runs through
                 the canonical upgrade pipeline (see ThemeFleetUpgradeService::dispatchPackageUpgrade).
                 Pending versions are listed read-only; there is no apply form. --}}
            <div class="ob-panel ob-panel-warn" role="status" data-fleet-package-upgrade="disabled" style="border:1px solid #fde68a;background:#fffbeb;border-radius:var(--ob-radius);padding:.85rem 1rem;margin-bottom:1rem;font-size:.85rem;color:#92400e">
                <strong>ارتقای پکیج موقتاً غیرفعال است.</strong>
                {{ \App\Services\ThemeBuilder\ThemeFleetUpgradeService::PACKAGE_UPGRADE_DISABLED_REASON }}
            </div>
            @if(!empty($fleetUpdates['packages']))
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:.85rem;text-align:right">
                        <thead>
                            <tr style="border-bottom:2px solid var(--ob-border);background:#f8fafc">
                                <th style="padding:.65rem">دامنه و سایت مشتری</th>
                                <th style="padding:.65rem">نسخه فعلی</th>
                                <th style="padding:.65rem">نسخه فعال پکیج</th>
                                <th style="padding:.65rem">حجم</th>
                                <th style="padding:.65rem">وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fleetUpdates['packages'] as $pkg)
                                <tr style="border-bottom:1px solid var(--ob-border)">
                                    <td style="padding:.75rem">
                                        <strong>{{ $pkg['site_title'] }}</strong>
                                        <div style="font-size:.75rem;color:var(--ob-muted);direction:ltr;text-align:right">{{ $pkg['domain'] }}</div>
                                    </td>
                                    <td style="padding:.75rem"><span style="font-family:monospace">v{{ $pkg['current_version'] }}</span></td>
                                    <td style="padding:.75rem"><span style="font-family:monospace;font-weight:700">v{{ $pkg['available_version'] }}</span></td>
                                    <td style="padding:.75rem"><span class="ob-badge ob-badge-outline">{{ $pkg['size_human'] ?? '—' }}</span></td>
                                    <td style="padding:.75rem;color:var(--ob-muted)">در انتظار پایپلاین استاندارد ارتقا</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif(\App\Models\CustomerInstallationPackage::active() === null)
                <div style="background:#fef2f2;border:1px dashed #fecaca;border-radius:var(--ob-radius);padding:1.25rem;text-align:center;color:#991b1b;font-size:.85rem;margin-bottom:1rem">
                    هیچ بستهٔ نصب فعالی ثبت نشده است؛ بسته را با فرمان install-packages ثبت و فعال کنید.
                </div>
            @else
                <div style="background:#f8fafc;border:1px dashed var(--ob-border);border-radius:var(--ob-radius);padding:1.25rem;text-align:center;color:var(--ob-muted);font-size:.85rem;margin-bottom:1rem">
                    ✅ تمامی سایت‌ها آخرین پکیج هسته فعال پلتفرم را دارند.
                </div>
            @endif

            {{-- 3. Comprehensive Audit Log & Rollback Table --}}
            <h3 style="font-size:.95rem;font-weight:700;margin:2.5rem 0 .5rem">۳. تاریخچه حسابرسی به‌روزرسانی‌ها و بازگشت به نسخه قبل (Audit Log & Rollback):</h3>
            @if(empty($auditHistory) || $auditHistory->isEmpty())
                <div style="background:#f8fafc;border:1px dashed var(--ob-border);border-radius:var(--ob-radius);padding:1.5rem;text-align:center;color:var(--ob-muted);font-size:.85rem">
                    تاکنون هیچ عملیات به‌روزرسانی یا بازگردانی روی ناوگان ثبت نشده است.
                </div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:.85rem;text-align:right">
                        <thead>
                            <tr style="border-bottom:2px solid var(--ob-border);background:#f8fafc">
                                <th style="padding:.65rem">شناسه</th>
                                <th style="padding:.65rem">سایت و دامنه</th>
                                <th style="padding:.65rem">نوع تغییر</th>
                                <th style="padding:.65rem">نسخه‌ها (قبلی ← جدید)</th>
                                <th style="padding:.65rem">تاریخ و ساعت</th>
                                <th style="padding:.65rem">کاربر اجراکننده</th>
                                <th style="padding:.65rem">جزئیات و لاگ تغییرات</th>
                                <th style="padding:.65rem">وضعیت</th>
                                <th style="padding:.65rem;text-align:center">بازیابی (Rollback)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($auditHistory as $log)
                                <tr style="border-bottom:1px solid var(--ob-border);background:{{ $log->is_rollback ? '#fefce8' : 'inherit' }}">
                                    <td style="padding:.75rem;font-family:monospace;font-weight:700">#{{ $log->id }}</td>
                                    <td style="padding:.75rem">
                                        <strong>{{ $log->site?->title ?? 'سایت نامشخص' }}</strong>
                                        <div style="font-size:.75rem;color:var(--ob-muted);direction:ltr;text-align:right">{{ $log->site?->domain }}</div>
                                    </td>
                                    <td style="padding:.75rem">
                                        @if($log->is_rollback)
                                            <span style="background:#fef3c7;color:#92400e;padding:.2rem .45rem;border-radius:4px;font-size:.75rem;font-weight:700">🔄 بازیابی (Rollback)</span>
                                        @elseif($log->update_type === 'package')
                                            <span style="background:#e0e7ff;color:#3730a3;padding:.2rem .45rem;border-radius:4px;font-size:.75rem;font-weight:600">📦 پکیج هسته</span>
                                        @else
                                            <span style="background:#dbeafe;color:#1e40af;padding:.2rem .45rem;border-radius:4px;font-size:.75rem;font-weight:600">🎨 قالب و ویجت</span>
                                        @endif
                                    </td>
                                    <td style="padding:.75rem;font-family:monospace;font-size:.8rem">
                                        @if(!empty($log->component_versions['theme']))
                                            <div>قالب: {{ $log->component_versions['theme']['from'] }} ← {{ $log->component_versions['theme']['to'] }}</div>
                                        @elseif($log->fromRelease || $log->toRelease)
                                            <div>قالب: {{ $log->fromRelease?->version ?? '—' }} ← {{ $log->toRelease?->version ?? '—' }}</div>
                                        @endif
                                        @if(!empty($log->component_versions['customer_core']))
                                            <div style="color:#059669">هسته: {{ $log->component_versions['customer_core']['from'] }} ← {{ $log->component_versions['customer_core']['to'] }}</div>
                                        @endif
                                    </td>
                                    <td style="padding:.75rem;font-size:.75rem;color:var(--ob-muted)">
                                        {{ $log->started_at ? $log->started_at->format('Y-m-d H:i') : ($log->created_at ? $log->created_at->format('Y-m-d H:i') : '—') }}
                                    </td>
                                    <td style="padding:.75rem;font-size:.8rem">
                                        {{ $log->dispatchedBy?->name ?? 'سیستم / خودکار' }}
                                    </td>
                                    <td style="padding:.75rem;font-size:.75rem;color:#475569;max-width:260px">
                                        <div style="white-space:pre-line;line-height:1.3">{{ Str::limit($log->changelog_summary ?: 'ارتقای غیرمخرب با موفقیت اعمال شد.', 120) }}</div>
                                        @if($log->revision_number)
                                            <div style="font-size:.7rem;color:var(--ob-muted);margin-top:.2rem">شماره بازبینی مشتری: <strong>#{{ $log->revision_number }}</strong></div>
                                        @endif
                                    </td>
                                    <td style="padding:.75rem">
                                        @if($log->status === 'completed')
                                            <span style="color:#059669;font-weight:700">✅ موفق</span>
                                        @elseif($log->status === 'failed')
                                            <span style="color:#dc2626;font-weight:700" title="{{ $log->failure_reason }}">❌ خطا</span>
                                        @else
                                            <span style="color:#d97706;font-weight:700">⏳ در حال اجرا</span>
                                        @endif
                                    </td>
                                    <td style="padding:.75rem;text-align:center">
                                        @if($log->status === 'completed' && !$log->is_rollback && $log->revision_number)
                                            <form method="POST" action="{{ route('admin.onboarding.fleet-updates.rollback') }}" onsubmit="return confirm('آیا از بازگشت به نسخه قبل (Rollback) برای سایت «{{ $log->site?->title }}» اطمینان دارید؟ تمام تنظیمات به اسنپ‌شات قبلی بازگردانده خواهد شد.');">
                                                @csrf
                                                <input type="hidden" name="update_id" value="{{ $log->id }}">
                                                <button type="submit" class="ob-btn ob-btn-ghost" style="font-size:.75rem;padding:.3rem .6rem;color:#b45309;border:1px solid #fde68a;background:#fffbeb">
                                                    ↩️ بازگشت به نسخه قبل
                                                </button>
                                            </form>
                                        @else
                                            <span style="font-size:.75rem;color:var(--ob-muted)">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @else
        {{-- ── Standard Onboarding Form ── --}}

    @if($errors->any())
        <div class="ob-panel ob-panel-err" role="alert" style="margin-block-end:1rem">
            <div>
                <strong>ثبت انجام نشد.</strong> موارد زیر را اصلاح کنید:
                <ul style="margin:.25rem 0 0;padding-inline-start:1.1rem">
                    @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            </div>
        </div>
    @endif

    <ol class="ob-steps" aria-label="مراحل راه‌اندازی">
        <template x-for="(label, i) in stepLabels" :key="i">
            <li>
                <button type="button" class="ob-step"
                        :data-state="i + 1 === step ? 'current' : (i + 1 < step ? 'done' : 'todo')"
                        :aria-current="i + 1 === step ? 'step' : null"
                        :disabled="i + 1 > step"
                        @click="i + 1 < step && go(i + 1)">
                    <span class="ob-step-num" x-text="i + 1 < step ? '✓' : fa(i + 1)"></span>
                    <span class="ob-step-label" x-text="label"></span>
                </button>
            </li>
        </template>
    </ol>

    <form method="POST" action="{{ route('admin.onboarding.store') }}" x-ref="form" @submit="submitting = true" novalidate>
        @csrf

        {{-- ── Step 1: customer ───────────────────────────────────────── --}}
        <section class="ob-card" x-show="step === 1" x-ref="step1" aria-labelledby="ob-s1">
            <h2 id="ob-s1">مشتری</h2>
            <p class="ob-lead">هویت مشتری شمارهٔ موبایل اوست. اگر این موبایل قبلاً ثبت شده باشد، سایت جدید به همان مشتری اضافه می‌شود؛ یک مشتری می‌تواند هر تعداد سایت داشته باشد.</p>

            <div class="ob-grid">
                <div class="ob-field">
                    <label for="ob-mobile">شمارهٔ موبایل <span class="ob-req" aria-hidden="true">*</span></label>
                    <input id="ob-mobile" name="mobile" type="tel" inputmode="numeric" autocomplete="tel" class="ob-input ob-ltr"
                           placeholder="09123456789" x-model="form.mobile" @input.debounce.400ms="lookupCustomer()"
                           :aria-invalid="customer.state === 'invalid'" aria-describedby="ob-mobile-status" required>
                    <div id="ob-mobile-status" aria-live="polite">
                        <p class="ob-hint" x-show="customer.state === 'loading'" x-cloak>در حال بررسی…</p>
                        <p class="ob-err" x-show="customer.state === 'invalid'" x-cloak>شماره موبایل باید با ۰۹ شروع شود و ۱۱ رقم باشد.</p>
                        <p class="ob-err" x-show="customer.state === 'error'" x-cloak>بررسی موبایل ممکن نشد؛ دوباره تلاش کنید.</p>
                    </div>
                    @error('mobile')<p class="ob-err">{{ $message }}</p>@enderror
                </div>

                <div class="ob-panel ob-panel-ok" x-show="customer.state === 'existing' && !customer.data?.trashed" x-cloak>
                    <div>
                        <strong x-text="'مشتری موجود: ' + (customer.data?.name || '')"></strong>
                        <span x-show="customer.data?.company_name" x-text="' — ' + customer.data?.company_name"></span>
                        <div x-text="customer.data?.sites?.length ? ('سایت‌های فعلی این مشتری (' + fa(customer.data.sites.length) + '):') : 'این مشتری هنوز سایتی ندارد.'"></div>
                        <div class="ob-sites">
                            <template x-for="s in (customer.data?.sites || [])" :key="s.id">
                                <span class="ob-badge" :title="s.title"><span class="ob-ltr" x-text="s.domain || s.title"></span></span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="ob-panel ob-panel-err" x-show="customer.state === 'existing' && customer.data?.trashed" x-cloak role="alert">
                    این موبایل متعلق به مشتری حذف‌شده است؛ ابتدا او را از فهرست مشتریان بازیابی کنید.
                </div>

                <div class="ob-grid ob-grid-2" x-show="customer.state === 'new'" x-cloak>
                    <div class="ob-panel ob-panel-info" style="grid-column:1/-1">این موبایل تازه است؛ مشتری جدید با همین شماره ساخته می‌شود.</div>
                    <div class="ob-field">
                        <label for="ob-name">نام و نام خانوادگی <span class="ob-req" aria-hidden="true">*</span></label>
                        <input id="ob-name" name="customer_name" type="text" class="ob-input" autocomplete="name" x-model="form.customer_name" :aria-invalid="touched.step1 && !form.customer_name.trim()">
                        <p class="ob-err" x-show="touched.step1 && !form.customer_name.trim()" x-cloak>نام مشتری جدید الزامی است.</p>
                        @error('customer_name')<p class="ob-err">{{ $message }}</p>@enderror
                    </div>
                    <div class="ob-field">
                        <label for="ob-company">نام شرکت یا برند</label>
                        <input id="ob-company" name="company_name" type="text" class="ob-input" autocomplete="organization" x-model="form.company_name">
                    </div>
                </div>
            </div>
        </section>

        {{-- ── Step 2: site & domain ──────────────────────────────────── --}}
        <section class="ob-card" x-show="step === 2" x-cloak aria-labelledby="ob-s2">
            <h2 id="ob-s2">سایت و دامنه</h2>
            <p class="ob-lead">رکورد DNS زیردامنه‌های پلتفرم خودکار ساخته می‌شود. دامنهٔ اختصاصی باید از قبل به سرور اشاره کند.</p>

            <div class="ob-grid">
                <div class="ob-field">
                    <label for="ob-title">نام سایت <span class="ob-req" aria-hidden="true">*</span></label>
                    <input id="ob-title" name="site_title" type="text" class="ob-input" maxlength="120" x-model="form.site_title" placeholder="مثال: خورشید انرژی پارس" :aria-invalid="touched.step2 && !titleValid()">
                    <p class="ob-err" x-show="touched.step2 && !titleValid()" x-cloak>نام سایت الزامی است و نباید شامل " \ $ ` باشد.</p>
                    @error('site_title')<p class="ob-err">{{ $message }}</p>@enderror
                </div>

                <div class="ob-field">
                    <span id="ob-mode-label" style="display:block;font-size:.85rem;font-weight:600;margin-block-end:.4rem">نوع دامنه</span>
                    <div class="ob-seg" role="group" aria-labelledby="ob-mode-label">
                        <button type="button" :aria-pressed="form.domain_mode === 'subdomain'" :disabled="!options.root_domain" @click="setMode('subdomain')">زیردامنهٔ پلتفرم</button>
                        <button type="button" :aria-pressed="form.domain_mode === 'custom'" @click="setMode('custom')">دامنهٔ اختصاصی</button>
                    </div>
                    <input type="hidden" name="domain_mode" :value="form.domain_mode">
                    <p class="ob-hint" x-show="!options.root_domain" x-cloak>دامنهٔ اصلی پلتفرم در تنظیمات DNS Provider ثبت نشده؛ فقط دامنهٔ اختصاصی ممکن است.</p>
                </div>

                <div class="ob-field">
                    <label for="ob-domain" x-text="form.domain_mode === 'subdomain' ? 'زیردامنه' : 'دامنه'"></label>
                    <div :class="form.domain_mode === 'subdomain' ? 'ob-affix' : ''">
                        <input id="ob-domain" name="domain" type="text" class="ob-input ob-ltr" autocomplete="off" spellcheck="false"
                               x-model="form.domain" @input.debounce.500ms="checkDomain()"
                               :placeholder="form.domain_mode === 'subdomain' ? 'shop' : 'example.ir'"
                               :aria-invalid="domain.state === 'error'" aria-describedby="ob-domain-status">
                        <span x-show="form.domain_mode === 'subdomain'" x-text="'.' + options.root_domain"></span>
                    </div>
                    <div id="ob-domain-status" aria-live="polite">
                        <p class="ob-hint" x-show="domain.state === 'loading'" x-cloak>در حال بررسی دامنه…</p>
                        <p class="ob-ok-text" x-show="domain.state === 'ok'" x-cloak>
                            <span class="ob-ltr" x-text="domain.domain"></span> آزاد است<span x-show="domain.platform"> — DNS خودکار ساخته می‌شود</span>.
                        </p>
                        <p class="ob-err" x-show="domain.state === 'error'" x-cloak x-text="domain.error"></p>
                    </div>
                    @error('domain')<p class="ob-err">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- ── Step 3: business, licence & modules ───────────────────────── --}}
        <section class="ob-card" x-show="step === 3" x-cloak aria-labelledby="ob-s3">
            <h2 id="ob-s3">کسب‌وکار، لایسنس و انتخاب ماژول‌ها</h2>
            <p class="ob-lead">با انتخاب حوزهٔ کسب‌وکار، قالب مناسب و تمام ماژول‌های مورد نیاز به صورت خودکار انتخاب می‌شوند. امکان انتخاب کلی، دسته‌ای و تکی فراهم است تا نیازی به تنظیمات دستی پس از نصب نباشد.</p>

            <div class="ob-grid">
                <div class="ob-field">
                    <span style="display:block;font-size:.85rem;font-weight:600;margin-block-end:.4rem" id="ob-vertical-label">حوزهٔ کسب‌وکار</span>
                    <div class="ob-choices" role="radiogroup" aria-labelledby="ob-vertical-label">
                        <button type="button" class="ob-choice" role="radio" :aria-checked="form.business_vertical === ''" @click="setVertical('')">
                            <strong>بدون حوزهٔ اختصاصی</strong><small>فقط قالب‌ها و ماژول‌های عمومی</small>
                        </button>
                        <template x-for="(p, key) in options.profiles" :key="key">
                            <button type="button" class="ob-choice" role="radio" :aria-checked="form.business_vertical === key" @click="setVertical(key)">
                                <strong x-text="p.label"></strong>
                                <small x-show="p.kit_ids.length || p.theme_id" x-text="profileSummary(p)"></small>
                            </button>
                        </template>
                    </div>
                    <input type="hidden" name="business_vertical" :value="form.business_vertical">
                    @error('business_vertical')<p class="ob-err">{{ $message }}</p>@enderror
                </div>

                <div class="ob-field">
                    <span style="display:block;font-size:.85rem;font-weight:600;margin-block-end:.4rem" id="ob-license-label">نوع لایسنس</span>
                    <div class="ob-seg" role="group" aria-labelledby="ob-license-label">
                        <template x-for="(label, key) in options.license_types" :key="key">
                            <button type="button" :aria-pressed="form.license_type === key" @click="form.license_type = key" x-text="label"></button>
                        </template>
                    </div>
                    <input type="hidden" name="license_type" :value="form.license_type">
                </div>

                {{-- All Modules (Categorized with Fast Bulk/Individual Toggle) --}}
                <div class="ob-field" style="border-top:1px solid var(--ob-border);padding-top:1rem;margin-top:.5rem">
                    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:.75rem;margin-bottom:.75rem">
                        <div>
                            <span style="display:block;font-size:.9rem;font-weight:700" id="ob-modules-label">
                                ماژول‌ها و امکانات فعال سایت
                                <span class="ob-badge ob-badge-accent" style="margin-right:.5rem" x-text="fa(form.active_module_ids.length) + ' فعال'"></span>
                            </span>
                            <small style="color:var(--ob-muted);font-size:.78rem">ماژول‌های انتخاب‌شده مستقیماً در لحظهٔ نصب روی سایت فعال می‌شوند.</small>
                        </div>
                        <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                            <button type="button" class="ob-btn ob-btn-ghost" style="font-size:.75rem;padding:.3rem .6rem;min-height:2rem" @click="selectDefaultVerticalModules()">
                                🎯 پیشنهاد این کسب‌وکار
                            </button>
                            <button type="button" class="ob-btn ob-btn-ghost" style="font-size:.75rem;padding:.3rem .6rem;min-height:2rem" @click="selectAllModules()">
                                ✅ انتخاب همه
                            </button>
                            <button type="button" class="ob-btn ob-btn-ghost" style="font-size:.75rem;padding:.3rem .6rem;min-height:2rem" @click="deselectAllModules()">
                                ❌ لغو همه
                            </button>
                        </div>
                    </div>

                    {{-- Category filter pills --}}
                    <div class="ob-seg" style="margin-bottom:1rem;display:flex;gap:.25rem;overflow-x:auto;padding:.2rem">
                        <button type="button" :aria-pressed="activeModuleCategory === 'all'" @click="activeModuleCategory = 'all'" style="font-size:.8rem;padding:.35rem .75rem">
                            همه ماژول‌ها (<span x-text="fa(options.modules.length)"></span>)
                        </button>
                        <template x-for="(cat, cKey) in options.module_categories" :key="cKey">
                            <button type="button" :aria-pressed="activeModuleCategory === cKey" @click="activeModuleCategory = cKey" style="font-size:.8rem;padding:.35rem .75rem" x-text="cat.label">
                            </button>
                        </template>
                    </div>

                    {{-- Modules Grid --}}
                    <div class="ob-choices" role="group" aria-labelledby="ob-modules-label" style="grid-template-columns:repeat(auto-fill,minmax(min(100%,14rem),1fr))">
                        <template x-for="mod in filteredModules()" :key="mod.id">
                            <button type="button" class="ob-choice" :aria-pressed="form.active_module_ids.includes(mod.id)"
                                    @click="toggleActiveModule(mod.id)">
                                <div style="display:flex;align-items:center;justify-content:space-between;width:100%">
                                    <strong x-text="mod.title"></strong>
                                    <span class="ob-badge ob-badge-ok" x-show="form.active_module_ids.includes(mod.id)">فعال</span>
                                </div>
                                <small x-show="mod.description" x-text="mod.description" style="line-height:1.5"></small>
                                <div style="display:flex;gap:.35rem;margin-top:auto;padding-top:.3rem">
                                    <span x-show="mod.is_kit" class="ob-badge ob-badge-accent">کیت اختصاصی</span>
                                    <span x-show="mod.is_paid" class="ob-badge" style="color:#d97706;border-color:#fde68a">پرمیوم</span>
                                </div>
                            </button>
                        </template>
                    </div>

                    <template x-for="id in form.active_module_ids" :key="'m' + id">
                        <input type="hidden" name="active_module_ids[]" :value="id">
                    </template>
                    @error('active_module_ids')<p class="ob-err">{{ $message }}</p>@enderror
                </div>

                {{-- Kits (Preserved for compatibility and entitlement linkage) --}}
                <div class="ob-field" x-show="options.kits.length" style="border-top:1px dashed var(--ob-border);padding-top:1rem">
                    <span style="display:block;font-size:.85rem;font-weight:600;margin-block-end:.4rem" id="ob-kits-label">کیت‌های لایسنس پلتفرم</span>
                    <div class="ob-choices" role="group" aria-labelledby="ob-kits-label">
                        <template x-for="kit in options.kits" :key="kit.id">
                            <button type="button" class="ob-choice" :aria-pressed="form.kit_module_ids.includes(kit.id)"
                                    :aria-disabled="kit.vertical && kit.vertical !== form.business_vertical"
                                    @click="toggleKit(kit)">
                                <strong x-text="kit.title"></strong>
                                <small x-show="kit.description" x-text="kit.description"></small>
                                <span x-show="kit.vertical" class="ob-badge" style="align-self:flex-start" x-text="'ویژهٔ ' + (options.profiles[kit.vertical]?.label || kit.vertical)"></span>
                            </button>
                        </template>
                    </div>
                    <template x-for="id in form.kit_module_ids" :key="'k' + id"><input type="hidden" name="kit_module_ids[]" :value="id"></template>
                    <p class="ob-hint">کیت‌های لایسنس به صورت یکپارچه با ماژول‌های فعال سایت لایسنس‌گذاری می‌شوند.</p>
                    @error('kit_module_ids')<p class="ob-err">{{ $message }}</p>@enderror
                </div>

                <div class="ob-field" style="display:none">
                    <template x-for="m in form.base_modules" :key="'b' + m"><input type="hidden" name="base_modules[]" :value="m"></template>
                </div>
            </div>
        </section>

        {{-- ── Step 4: theme ──────────────────────────────────────────── --}}
        <section class="ob-card" x-show="step === 4" x-cloak aria-labelledby="ob-s4">
            <h2 id="ob-s4">قالب سایت</h2>
            <p class="ob-lead">قالب انتخابی — با صفحهٔ اصلی، سربرگ و پابرگ اختصاصی‌اش — پس از پایان نصب خودکار روی سایت اعمال می‌شود و طراحی از همان لحظه روی دامنه دیده می‌شود.</p>

            <div class="ob-panel ob-panel-warn" style="margin-block-end:1rem" x-show="profile()?.theme_status === 'unavailable'" x-cloak role="status">
                <p style="margin:0">
                    قالب اختصاصی این حوزه (<span class="ob-ltr" x-text="profile()?.theme_slug"></span>) قابل انتخاب نیست:
                    <strong x-text="themeReasonLabel(profile()?.theme_reason)"></strong>
                </p>
                <template x-if="(profile()?.theme_fix || []).length">
                    <div style="margin-block-start:.5rem">
                        <small>برای رفع، این دستورها را به ترتیب روی سرور پلتفرم اجرا کنید و صفحه را دوباره باز کنید:</small>
                        <ol style="margin:.35rem 0 0;padding-inline-start:1.25rem">
                            <template x-for="cmd in profile().theme_fix" :key="cmd">
                                <li><code class="ob-ltr" x-text="cmd"></code></li>
                            </template>
                        </ol>
                    </div>
                </template>
            </div>

            <div class="ob-choices" role="radiogroup" aria-label="قالب‌ها" style="grid-template-columns:repeat(auto-fill,minmax(min(100%,15rem),1fr))">
                <button type="button" class="ob-choice" role="radio" :aria-checked="form.theme_id === null" @click="form.theme_id = null">
                    <div class="ob-theme-img" aria-hidden="true"></div>
                    <strong>بدون قالب</strong><small>سایت با ظاهر پیش‌فرض راه‌اندازی می‌شود.</small>
                </button>
                <template x-for="t in options.themes" :key="t.id">
                    <button type="button" class="ob-choice" role="radio" :aria-checked="form.theme_id === t.id" @click="form.theme_id = t.id">
                        <img x-show="t.image" :src="t.image" alt="" class="ob-theme-img" loading="lazy">
                        <div x-show="!t.image" class="ob-theme-img" aria-hidden="true"></div>
                        <span style="display:flex;gap:.375rem;align-items:center;flex-wrap:wrap">
                            <strong x-text="t.name"></strong>
                            <span class="ob-badge ob-badge-accent" x-show="profile()?.theme_id === t.id">پیشنهاد این حوزه</span>
                        </span>
                        <small x-text="(t.description || '').slice(0, 110)"></small>
                        <span class="ob-badge" style="align-self:flex-start" x-text="fa(t.pages) + ' صفحه'"></span>
                    </button>
                </template>
            </div>
            <input type="hidden" name="theme_id" :value="form.theme_id ?? ''">
            <p class="ob-hint" x-show="!options.themes.length">هیچ قالب منتشرشده و واجد شرایط تجاری‌ای وجود ندارد.</p>
            @error('theme_id')<p class="ob-err">{{ $message }}</p>@enderror
        </section>

        {{-- ── Step 5: server, host/db & resource governance ────────────────────── --}}
        <section class="ob-card" x-show="step === 5" x-cloak aria-labelledby="ob-s5">
            <h2 id="ob-s5">سرور، اطلاعات دیتابیس و مدیریت منابع سخت‌افزاری</h2>
            <p class="ob-lead">با انتخاب سرور، اطلاعات پایگاه‌داده و مسیر وب به صورت هوشمند پیشنهاد می‌شوند. همچنین پلن‌های سهمیه‌بندی منابع (اشتراکی/ایمن) به طور دقیق و برخط کنترل می‌گردند.</p>

            <div class="ob-grid">
                {{-- 1. Server Selection --}}
                <div class="ob-field">
                    <span style="display:block;font-size:.85rem;font-weight:600;margin-block-end:.4rem" id="ob-server-label">سرور مقصد <span class="ob-req" aria-hidden="true">*</span></span>
                    <div class="ob-choices" role="radiogroup" aria-labelledby="ob-server-label">
                        <template x-for="s in options.servers" :key="s.id">
                            <button type="button" class="ob-choice" role="radio" :aria-checked="form.remote_server_id === s.id" :disabled="!s.implemented" @click="selectServer(s.id)">
                                <strong x-text="s.label"></strong>
                                <small class="ob-ltr" x-text="s.host"></small>
                                <span class="ob-badge" style="align-self:flex-start" x-text="s.implemented ? s.type : 'درایور پشتیبانی نمی‌شود'"></span>
                            </button>
                        </template>
                    </div>
                    <input type="hidden" name="remote_server_id" :value="form.remote_server_id ?? ''">
                    <div class="ob-panel ob-panel-warn" x-show="!options.servers.length" x-cloak>
                        هنوز سروری ثبت نشده است. <a href="{{ route('admin.infrastructure.customer-deployment.servers.create') }}" target="_blank" rel="noopener" style="font-weight:700;text-decoration:underline">افزودن سرور</a>
                    </div>
                    @error('remote_server_id')<p class="ob-err">{{ $message }}</p>@enderror
                </div>

                {{-- Live Server Capacity Monitor Banner --}}
                <div x-show="serverCapacity" x-cloak class="ob-panel" :class="serverCapacity?.has_headroom ? 'ob-panel-ok' : 'ob-panel-warn'" style="border:1px solid var(--ob-border)">
                    <div style="width:100%">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.35rem">
                            <strong style="font-size:.85rem" x-text="'وضعیت منابع برخط سرور (' + (serverCapacity?.server?.label || '') + ')'"></strong>
                            <span class="ob-badge" :class="serverCapacity?.has_headroom ? 'ob-badge-ok' : 'ob-badge-accent'" x-text="serverCapacity?.has_headroom ? 'ظرفیت سخت‌افزاری ایمن' : 'هشدار پر بودن یا ریسک سرور'"></span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.5rem;font-size:.78rem">
                            <div>رم آزاد فیزیکی: <strong class="ob-ltr" x-text="serverCapacity?.ram?.free_mb + ' MB'"></strong> / کل: <span class="ob-ltr" x-text="serverCapacity?.ram?.total_mb + ' MB'"></span></div>
                            <div>دیسک آزاد فیزیکی: <strong class="ob-ltr" x-text="serverCapacity?.disk?.free_gb + ' GB'"></strong> / کل: <span class="ob-ltr" x-text="serverCapacity?.disk?.total_gb + ' GB'"></span></div>
                            <div>تعهد رم (Overcommit): <strong class="ob-ltr" x-text="serverCapacity?.ram?.overcommit_ratio + 'x'"></strong></div>
                            <div>تعداد سایت‌های سرور: <strong x-text="fa(serverCapacity?.sites_count || 0)"></strong></div>
                        </div>
                    </div>
                </div>

                {{-- 2. Host & Database Auto-Configuration --}}
                <div style="border:1px solid var(--ob-border);border-radius:12px;padding:1rem;background:var(--ob-soft);margin-top:.5rem">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
                        <strong style="font-size:.9rem">🗄️ مشخصات هاست و دیتابیس هوشمند</strong>
                        <button type="button" class="ob-btn ob-btn-ghost" style="font-size:.75rem;padding:.25rem .6rem;min-height:1.8rem" @click="fetchSuggestedInfrastructure()">
                            🔄 تولید مجدد مشخصات
                        </button>
                    </div>
                    <div class="ob-grid ob-grid-2">
                        <div class="ob-field">
                            <label for="ob-webroot">مسیر وب‌روت (Web Root Path) <span class="ob-req">*</span></label>
                            <input id="ob-webroot" name="web_root_path" type="text" class="ob-input ob-ltr" x-model="form.web_root_path" placeholder="/var/www/site_..." required>
                            @error('web_root_path')<p class="ob-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="ob-field">
                            <label for="ob-dbhost">میزبان دیتابیس (DB Host) <span class="ob-req">*</span></label>
                            <input id="ob-dbhost" name="db_host" type="text" class="ob-input ob-ltr" x-model="form.db_host" placeholder="127.0.0.1" required>
                            @error('db_host')<p class="ob-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="ob-field">
                            <label for="ob-dbname">نام دیتابیس (DB Name) <span class="ob-req">*</span></label>
                            <input id="ob-dbname" name="db_name" type="text" class="ob-input ob-ltr" x-model="form.db_name" placeholder="site_..." required>
                            @error('db_name')<p class="ob-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="ob-field">
                            <label for="ob-dbuser">نام کاربر دیتابیس (DB User) <span class="ob-req">*</span></label>
                            <input id="ob-dbuser" name="db_user" type="text" class="ob-input ob-ltr" x-model="form.db_user" placeholder="site_..." required>
                            @error('db_user')<p class="ob-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="ob-field" style="grid-column:1/-1">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.3rem">
                                <label for="ob-dbpass" style="margin:0">کلمه عبور امن دیتابیس (DB Password)</label>
                                <button type="button" class="ob-link" style="font-size:.75rem" @click="generateRandomPassword()">🎲 کلمه عبور تصادفی جدید</button>
                            </div>
                            <div class="ob-affix">
                                <input id="ob-dbpass" name="db_password" :type="showDbPass ? 'text' : 'password'" class="ob-input ob-ltr" x-model="form.db_password" placeholder="کلمه عبور امن (اختیاری جهت شخصی‌سازی)">
                                <button type="button" @click="showDbPass = !showDbPass" style="border:1px solid var(--ob-border);border-inline-start:0;background:var(--ob-surface);padding:0 .75rem;cursor:pointer;font-size:.8rem">
                                    <span x-text="showDbPass ? 'مخفی' : 'نمایش'"></span>
                                </button>
                            </div>
                            <small class="ob-hint">در صورت خالی ماندن، پایپلاین استقرار یک رمز عبور تصادفی ۳۲ کاراکتری تولید و در فایل .env ذخیره می‌کند.</small>
                            @error('db_password')<p class="ob-err">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- 3. Hardware Resource Governance & Shared Pool (Overcommit) --}}
                <div style="border:1px solid var(--ob-border);border-radius:12px;padding:1rem;margin-top:.5rem">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
                        <strong style="font-size:.9rem">⚡ سهمیه‌بندی منابع سخت‌افزاری و استخر اشتراکی (Resource Governance)</strong>
                        <span class="ob-badge ob-badge-accent">سیاست دیسک نرم: ۷۰٪ + Grace Period ۷ روزه</span>
                    </div>

                    {{-- Resource Plan presets --}}
                    <div class="ob-choices" role="radiogroup" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:1rem">
                        <template x-for="(plan, pKey) in options.resource_plans" :key="pKey">
                            <button type="button" class="ob-choice" role="radio" :aria-checked="form.resource_plan === pKey" @click="applyResourcePlan(pKey)">
                                <strong x-text="plan.label"></strong>
                                <small x-text="plan.description"></small>
                                <div style="font-size:.75rem;color:var(--ob-muted);margin-top:.35rem;line-height:1.6">
                                    رم: <span class="ob-ltr" x-text="plan.ram_mb + ' MB'"></span> | دیسک: <span class="ob-ltr" x-text="plan.disk_gb + ' GB'"></span>
                                </div>
                            </button>
                        </template>
                        <button type="button" class="ob-choice" role="radio" :aria-checked="form.resource_plan === 'custom'" @click="form.resource_plan = 'custom'">
                            <strong>سفارشی (Custom)</strong>
                            <small>تنظیم دستی تمامی مقادیر منابع</small>
                        </button>
                    </div>
                    <input type="hidden" name="resource_plan" :value="form.resource_plan">

                    {{-- Custom Quota Inputs --}}
                    <div class="ob-grid ob-grid-2" style="background:#f8fafc;padding:.85rem;border-radius:10px;border:1px solid var(--ob-border)">
                        <div class="ob-field">
                            <label for="ob-ram">سقف رم مجاز (RAM Limit - MB) <span class="ob-req">*</span></label>
                            <input id="ob-ram" name="ram_mb" type="number" min="512" max="32768" step="256" class="ob-input ob-ltr" x-model.number="form.ram_mb" required>
                            <span class="ob-hint" x-text="'معادل ' + (form.ram_mb / 1024).toFixed(1) + ' گیگابایت (استخر اشتراکی)'"></span>
                            @error('ram_mb')<p class="ob-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="ob-field">
                            <label for="ob-cpu">هسته پردازنده مجاز (CPU Cores) <span class="ob-req">*</span></label>
                            <input id="ob-cpu" name="cpu_cores" type="number" min="0.5" max="16" step="0.5" class="ob-input ob-ltr" x-model.number="form.cpu_cores" required>
                            <span class="ob-hint">سهمیه پردازش پویا</span>
                            @error('cpu_cores')<p class="ob-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="ob-field">
                            <label for="ob-disk">دیسک سخت (Hard Disk Quota - GB) <span class="ob-req">*</span></label>
                            <input id="ob-disk" name="disk_gb" type="number" min="1" max="1000" step="1" class="ob-input ob-ltr" x-model.number="form.disk_gb" @input="updateHardDisk()" required>
                            <span class="ob-hint">سقف نهایی فیزیکی ذخیره‌سازی</span>
                            @error('disk_gb')<p class="ob-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="ob-field">
                            <label for="ob-softdisk">دیسک نرم (Soft Disk Quota - GB) <span class="ob-req">*</span></label>
                            <input id="ob-softdisk" name="soft_disk_gb" type="number" min="0.5" max="1000" step="0.5" class="ob-input ob-ltr" x-model.number="form.soft_disk_gb" required>
                            <span class="ob-hint" x-text="'پیش‌فرض ۷۰٪ (' + (form.disk_gb * 0.70).toFixed(1) + ' GB) — هشدار به مشتری بدون قطعی سایت'"></span>
                            @error('soft_disk_gb')<p class="ob-err">{{ $message }}</p>@enderror
                        </div>
                        <div class="ob-field" style="grid-column:1/-1">
                            <label for="ob-bandwidth">ترافیک ماهانه مجاز (Bandwidth - GB)</label>
                            <input id="ob-bandwidth" name="bandwidth_gb" type="number" min="1" max="10000" step="5" class="ob-input ob-ltr" x-model.number="form.bandwidth_gb">
                            @error('bandwidth_gb')<p class="ob-err">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="ob-grid ob-grid-2">
                    <div class="ob-field">
                        <span style="display:block;font-size:.85rem;font-weight:600;margin-block-end:.4rem" id="ob-ssl-label">گواهی SSL</span>
                        <div class="ob-seg" role="group" aria-labelledby="ob-ssl-label">
                            <button type="button" :aria-pressed="form.ssl_enabled" @click="form.ssl_enabled = true">فعال (HTTPS)</button>
                            <button type="button" :aria-pressed="!form.ssl_enabled" @click="form.ssl_enabled = false">غیرفعال</button>
                        </div>
                        <input type="hidden" name="ssl_enabled" :value="form.ssl_enabled ? 1 : 0">
                        <p class="ob-err" x-show="!form.ssl_enabled && form.theme_id" x-cloak>اعمال خودکار قالب از طریق HTTPS انجام می‌شود؛ بدون SSL قالب روی سایت اعمال نخواهد شد.</p>
                    </div>
                    <div class="ob-field">
                        <label for="ob-backup">پشتیبان‌گیری</label>
                        <select id="ob-backup" name="backup_policy" class="ob-input" x-model="form.backup_policy">
                            <template x-for="(label, key) in options.backup_policies" :key="key"><option :value="key" x-text="label" :selected="form.backup_policy === key"></option></template>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── Step 6: review ─────────────────────────────────────────── --}}
        <section class="ob-card" x-show="step === 6" x-cloak aria-labelledby="ob-s6">
            <h2 id="ob-s6">بازبینی و اجرای استقرار</h2>
            <p class="ob-lead">خلاصهٔ نهایی تنظیمات هوشمند سایت و سهمیه‌بندی منابع. با تأیید، پایپلاین استقرار بدون نیاز به اقدامات تکراری اجرا می‌شود.</p>

            <div class="ob-summary">
                <section>
                    <header><h3>مشتری</h3><button type="button" class="ob-link" @click="go(1)">ویرایش</button></header>
                    <dl>
                        <dt>موبایل</dt><dd class="ob-ltr" x-text="customer.mobile || form.mobile"></dd>
                        <dt>مشتری</dt><dd x-text="customer.state === 'existing' ? (customer.data?.name + ' (موجود)') : (form.customer_name + ' (جدید)')"></dd>
                    </dl>
                </section>
                <section>
                    <header><h3>سایت</h3><button type="button" class="ob-link" @click="go(2)">ویرایش</button></header>
                    <dl>
                        <dt>نام</dt><dd x-text="form.site_title"></dd>
                        <dt>دامنه</dt><dd class="ob-ltr" x-text="domain.domain"></dd>
                    </dl>
                </section>
                <section>
                    <header><h3>ماژول‌ها و لایسنس</h3><button type="button" class="ob-link" @click="go(3)">ویرایش</button></header>
                    <dl>
                        <dt>لایسنس</dt><dd x-text="options.license_types[form.license_type]"></dd>
                        <dt>حوزه</dt><dd x-text="form.business_vertical ? options.profiles[form.business_vertical]?.label : 'عمومی'"></dd>
                        <dt>ماژول‌های فعال</dt><dd><span class="ob-badge ob-badge-ok" x-text="fa(form.active_module_ids.length) + ' ماژول'"></span> <span x-text="selectedModuleTitlesSummary()"></span></dd>
                        <dt>کیت‌های لایسنس</dt><dd x-text="selectedKitTitles() || '—'"></dd>
                    </dl>
                </section>
                <section>
                    <header><h3>قالب</h3><button type="button" class="ob-link" @click="go(4)">ویرایش</button></header>
                    <dl>
                        <dt>قالب</dt><dd x-text="themeName() || 'بدون قالب'"></dd>
                    </dl>
                </section>
                <section>
                    <header><h3>هاست و پایگاه داده</h3><button type="button" class="ob-link" @click="go(5)">ویرایش</button></header>
                    <dl>
                        <dt>سرور مقصد</dt><dd x-text="serverLabel()"></dd>
                        <dt>مسیر وب</dt><dd class="ob-ltr" x-text="form.web_root_path || '(پیش‌فرض سرور)'"></dd>
                        <dt>دیتابیس / کاربر</dt><dd class="ob-ltr" x-text="(form.db_name || '—') + ' @ ' + (form.db_user || '—')"></dd>
                        <dt>میزبان دیتابیس</dt><dd class="ob-ltr" x-text="form.db_host || '127.0.0.1'"></dd>
                        <dt>SSL</dt><dd x-text="form.ssl_enabled ? 'فعال (HTTPS)' : 'غیرفعال'"></dd>
                    </dl>
                </section>
                <section>
                    <header><h3>سهمیه‌بندی منابع سخت‌افزاری</h3><button type="button" class="ob-link" @click="go(5)">ویرایش</button></header>
                    <dl>
                        <dt>پلن منابع</dt><dd x-text="resourcePlanLabel()"></dd>
                        <dt>رم تخصیصی</dt><dd class="ob-ltr" x-text="form.ram_mb + ' MB (' + (form.ram_mb / 1024).toFixed(1) + ' GB)'"></dd>
                        <dt>دیسک سخت / نرم</dt><dd class="ob-ltr" x-text="form.disk_gb + ' GB سخت / ' + form.soft_disk_gb + ' GB نرم (۷۰٪)'"></dd>
                        <dt>هسته‌های پردازنده</dt><dd class="ob-ltr" x-text="form.cpu_cores + ' Core'"></dd>
                        <dt>ترافیک ماهانه</dt><dd class="ob-ltr" x-text="form.bandwidth_gb + ' GB'"></dd>
                    </dl>
                </section>

                <div class="ob-panel ob-panel-info">
                    <div>
                        <strong>پس از تأیید، به‌ترتیب و خودکار:</strong>
                        <ul class="ob-pipeline" style="margin-block-start:.5rem">
                            <li>بررسی سرور، PHP، افزونه‌ها، ظرفیت رم و دیسک فیزیکی و مجوزها</li>
                            <li>ساخت دایرکتوری وب سفارشی، بارگذاری و استخراج بسته، تولید فایل .env و دیتابیس اختصاصی</li>
                            <li>رکورد DNS، پیکربندی Nginx روی سوکت PHP-FPM، صدور SSL و تنظیم مجوزهای www-data</li>
                            <li>صدور لایسنس سایت، فعال‌سازی مستقیم تمامی ماژول‌های انتخابی در پایگاه‌داده و سهمیه‌بندی منابع</li>
                            <li>اختصاص قالب، پاک‌سازی کش و نماها، و پس از آماده‌شدن سایت، اعمال صفحات قالب روی دامنه</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <div class="ob-nav">
            <button type="button" class="ob-btn ob-btn-ghost" x-show="step > 1" @click="go(step - 1)">مرحلهٔ قبل</button>
            <span x-show="step === 1"></span>
            <button type="button" class="ob-btn ob-btn-primary" x-show="step < 6" @click="next()" :disabled="busy()">
                <span class="ob-spin" x-show="busy()" aria-hidden="true"></span>
                مرحلهٔ بعد
            </button>
            <button type="submit" class="ob-btn ob-btn-primary" x-show="step === 6" x-cloak :disabled="submitting">
                <span class="ob-spin" x-show="submitting" aria-hidden="true"></span>
                <span x-text="submitting ? 'در حال ثبت…' : 'تأیید و راه‌اندازی خودکار'"></span>
            </button>
        </div>
    </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
function onboardingWizard(options, initial, prefill, errorStep) {
    const unsafe = /["\\$`\r\n]/;
    return {
        options,
        form: { ...initial },
        step: 1,
        submitting: false,
        touched: { step1: false, step2: false },
        stepLabels: ['مشتری', 'سایت و دامنه', 'لایسنس و کیت', 'قالب', 'سرور', 'بازبینی'],
        customer: { state: 'idle', data: null, mobile: null, seq: 0 },
        domain: { state: 'idle', domain: null, error: null, platform: false, seq: 0 },
        lookupUrl: @js(route('admin.onboarding.lookup-customer')),
        domainUrl: @js(route('admin.onboarding.check-domain')),

        activeModuleCategory: 'all',
        showDbPass: false,
        serverCapacity: null,
        capacityLoading: false,
        serverCapacityUrl: @js(route('admin.onboarding.server-capacity')),
        suggestInfraUrl: @js(route('admin.onboarding.suggest-infrastructure')),

        init() {
            if (prefill && prefill.valid) { this.applyLookup(prefill); }
            else if (this.form.mobile) { this.lookupCustomer(); }
            if (this.form.domain) { this.checkDomain(); }
            if (errorStep) { this.step = errorStep; }
            if (this.form.remote_server_id) {
                this.fetchServerCapacity(this.form.remote_server_id);
                if (!this.form.web_root_path || !this.form.db_name) {
                    this.fetchSuggestedInfrastructure();
                }
            }
            if (this.form.business_vertical && !this.form.active_module_ids.length) {
                this.selectDefaultVerticalModules();
            }
        },
        fa(n) { return Number(n).toLocaleString('fa-IR'); },

        // Module methods
        filteredModules() {
            if (this.activeModuleCategory === 'all') {
                return this.options.modules;
            }
            return this.options.modules.filter(m => m.category === this.activeModuleCategory);
        },
        toggleActiveModule(id) {
            const idx = this.form.active_module_ids.indexOf(id);
            if (idx === -1) {
                this.form.active_module_ids.push(id);
            } else {
                this.form.active_module_ids.splice(idx, 1);
            }
        },
        selectAllModules() {
            this.form.active_module_ids = this.options.modules.map(m => m.id);
        },
        deselectAllModules() {
            this.form.active_module_ids = [];
        },
        selectDefaultVerticalModules() {
            const vert = this.form.business_vertical || 'general';
            const defaults = this.options.vertical_modules[vert] || this.options.vertical_modules['general'] || [];
            this.form.active_module_ids = Array.from(new Set([...defaults]));
        },
        selectedModuleTitlesSummary() {
            if (!this.form.active_module_ids.length) { return 'هیچ ماژولی انتخاب نشده است.'; }
            const titles = this.options.modules
                .filter(m => this.form.active_module_ids.includes(m.id))
                .slice(0, 5)
                .map(m => m.title);
            const remaining = this.form.active_module_ids.length - titles.length;
            return '(' + titles.join('، ') + (remaining > 0 ? ' و ' + this.fa(remaining) + ' ماژول دیگر' : '') + ')';
        },

        // Infrastructure & Capacity methods
        async selectServer(serverId) {
            this.form.remote_server_id = serverId;
            await this.fetchServerCapacity(serverId);
            await this.fetchSuggestedInfrastructure();
        },
        async fetchServerCapacity(serverId) {
            if (!serverId) { return; }
            this.capacityLoading = true;
            try {
                const res = await fetch(`${this.serverCapacityUrl}?server_id=${serverId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    this.serverCapacity = await res.json();
                }
            } catch (e) {
                console.error('Failed to fetch server capacity', e);
            } finally {
                this.capacityLoading = false;
            }
        },
        async fetchSuggestedInfrastructure() {
            if (!this.form.remote_server_id) { return; }
            try {
                const params = new URLSearchParams({
                    server_id: this.form.remote_server_id,
                    domain: this.form.domain || 'site',
                    mobile: this.form.mobile || ''
                });
                const res = await fetch(`${this.suggestInfraUrl}?${params}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.form.web_root_path = data.web_root_path;
                    this.form.db_name = data.db_name;
                    this.form.db_user = data.db_user;
                    this.form.db_host = data.db_host || '127.0.0.1';
                    if (!this.form.db_password) {
                        this.form.db_password = data.db_password;
                    }
                }
            } catch (e) {
                console.error('Failed to suggest infrastructure', e);
            }
        },
        generateRandomPassword() {
            const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
            let pass = '';
            for (let i = 0; i < 20; i++) {
                pass += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            this.form.db_password = pass;
        },

        // Resource Governance methods
        applyResourcePlan(planKey) {
            this.form.resource_plan = planKey;
            const p = this.options.resource_plans[planKey];
            if (p) {
                this.form.ram_mb = p.ram_mb;
                this.form.disk_gb = p.disk_gb;
                this.form.soft_disk_gb = p.soft_disk_gb;
                this.form.cpu_cores = p.cpu_cores;
                this.form.bandwidth_gb = p.bandwidth_gb;
            }
        },
        updateHardDisk() {
            const ratio = this.options.default_soft_disk_ratio || 0.70;
            this.form.soft_disk_gb = Math.round(this.form.disk_gb * ratio * 10) / 10;
        },
        resourcePlanLabel() {
            if (this.form.resource_plan === 'custom') { return 'سفارشی (Custom)'; }
            return this.options.resource_plans[this.form.resource_plan]?.label || this.form.resource_plan;
        },

        async lookupCustomer() {
            const value = (this.form.mobile || '').trim();
            if (value.length < 10) { this.customer = { ...this.customer, state: value ? 'invalid' : 'idle', data: null }; return; }
            const seq = ++this.customer.seq;
            this.customer.state = 'loading';
            try {
                const res = await fetch(this.lookupUrl + '?mobile=' + encodeURIComponent(value), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { throw new Error(res.status); }
                const data = await res.json();
                if (seq === this.customer.seq) { this.applyLookup(data); }
            } catch (e) {
                if (seq === this.customer.seq) { this.customer.state = 'error'; }
            }
        },
        applyLookup(data) {
            if (!data.valid) { this.customer.state = 'invalid'; this.customer.data = null; return; }
            this.customer.mobile = data.mobile;
            this.customer.data = data.customer;
            this.customer.state = data.customer ? 'existing' : 'new';
        },

        setMode(mode) { if (this.form.domain_mode !== mode) { this.form.domain_mode = mode; this.checkDomain(); } },
        async checkDomain() {
            const value = (this.form.domain || '').trim();
            if (!value) { this.domain = { ...this.domain, state: 'idle', domain: null, error: null }; return; }
            const seq = ++this.domain.seq;
            this.domain.state = 'loading';
            try {
                const q = new URLSearchParams({ mode: this.form.domain_mode, domain: value });
                const res = await fetch(this.domainUrl + '?' + q, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) { throw new Error(res.status); }
                const data = await res.json();
                if (seq !== this.domain.seq) { return; }
                this.domain = { ...this.domain, state: data.ok ? 'ok' : 'error', domain: data.domain, error: data.error, platform: data.platform_subdomain };
            } catch (e) {
                if (seq === this.domain.seq) { this.domain = { ...this.domain, state: 'error', error: 'بررسی دامنه ممکن نشد؛ دوباره تلاش کنید.' }; }
            }
        },

        profile() { return this.form.business_vertical ? this.options.profiles[this.form.business_vertical] : null; },
        profileSummary(p) {
            const parts = [];
            if (p.kit_ids.length) { parts.push(this.fa(p.kit_ids.length) + ' کیت اختصاصی'); }
            if (p.theme_id) { parts.push('قالب اختصاصی'); }
            return parts.join(' + ');
        },
        setVertical(key) {
            const prev = this.form.business_vertical ? this.options.profiles[this.form.business_vertical] : null;
            this.form.business_vertical = key;
            // Kits bound to another vertical cannot work there: drop them.
            this.form.kit_module_ids = this.form.kit_module_ids.filter(id => {
                const kit = this.options.kits.find(k => k.id === id);
                return kit && (!kit.vertical || kit.vertical === key);
            });
            const p = this.profile();
            if (p) {
                p.kit_ids.forEach(id => { if (!this.form.kit_module_ids.includes(id)) { this.form.kit_module_ids.push(id); } });
                if (p.theme_id && (this.form.theme_id === null || this.form.theme_id === prev?.theme_id)) { this.form.theme_id = p.theme_id; }
            } else if (prev?.theme_id && this.form.theme_id === prev.theme_id) {
                this.form.theme_id = null;
            }
            this.selectDefaultVerticalModules();
        },
        toggleKit(kit) {
            if (kit.vertical && kit.vertical !== this.form.business_vertical) { return; }
            const i = this.form.kit_module_ids.indexOf(kit.id);
            i === -1 ? this.form.kit_module_ids.push(kit.id) : this.form.kit_module_ids.splice(i, 1);
        },
        toggleBase(key) {
            const i = this.form.base_modules.indexOf(key);
            i === -1 ? this.form.base_modules.push(key) : this.form.base_modules.splice(i, 1);
        },

        titleValid() { const t = (this.form.site_title || '').trim(); return t.length > 0 && !unsafe.test(t); },
        selectedKitTitles() { return this.options.kits.filter(k => this.form.kit_module_ids.includes(k.id)).map(k => k.title).join('، '); },
        themeName() { return this.options.themes.find(t => t.id === this.form.theme_id)?.name; },
        themeReasonLabel(reason) {
            return ({
                not_installed: 'این قالب روی پلتفرم نصب نشده است.',
                not_published: 'قالب نصب شده ولی منتشر نشده است.',
                no_current_release: 'قالب هنوز Release تجاری ندارد.',
                release_not_published: 'Release فعلی قالب منتشر نشده است.',
                release_theme_mismatch: 'Release فعلی به این قالب تعلق ندارد.',
                no_passing_quality_report: 'Release فعلی گزارش کیفیت تأییدشده ندارد.',
            })[reason] || 'وضعیت قالب نامشخص است؛ گزارش آن را در صفحهٔ قالب‌ها ببینید.';
        },
        serverLabel() { return this.options.servers.find(s => s.id === this.form.remote_server_id)?.label || '—'; },

        busy() { return (this.step === 1 && this.customer.state === 'loading') || (this.step === 2 && this.domain.state === 'loading'); },
        stepValid(n) {
            if (n === 1) {
                if (this.customer.state === 'existing') { return !this.customer.data?.trashed; }
                return this.customer.state === 'new' && this.form.customer_name.trim() !== '' && !unsafe.test(this.form.customer_name);
            }
            if (n === 2) { return this.titleValid() && this.domain.state === 'ok'; }
            if (n === 5) { return this.form.remote_server_id !== null; }
            return true;
        },
        next() {
            if (this.step === 1) { this.touched.step1 = true; if (this.customer.state === 'idle') { this.customer.state = 'invalid'; } }
            if (this.step === 2) { this.touched.step2 = true; if (this.domain.state === 'idle') { this.domain = { ...this.domain, state: 'error', error: 'دامنه یا زیردامنه را وارد کنید.' }; } }
            if (!this.stepValid(this.step)) { this.focusFirstInvalid(); return; }
            this.go(this.step + 1);
        },
        go(n) {
            this.step = n;
            this.$nextTick(() => {
                const card = this.$root.querySelectorAll('section.ob-card')[n - 1];
                card?.querySelector('h2')?.setAttribute('tabindex', '-1');
                card?.querySelector('h2')?.focus({ preventScroll: true });
                card?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
            });
        },
        focusFirstInvalid() {
            this.$nextTick(() => this.$root.querySelector('section.ob-card:not([style*="display: none"]) [aria-invalid="true"]')?.focus());
        },
    };
}
</script>
@endpush
