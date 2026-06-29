@extends('layouts.admin')

@section('header', 'حذف مشتری — تأیید')

@push('head')
<style>
.del-card{background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(15,23,42,0.04);padding:1.5rem;margin-bottom:1rem}
.policy-option{border:2px solid #e2e8f0;border-radius:.75rem;padding:1rem;cursor:pointer;transition:all .15s}
.policy-option:hover{border-color:#6366f1}
.policy-option.selected{border-color:#6366f1;background:#f5f3ff}
.policy-option input{display:none}
.del-warning{background:#fef2f2;border:1px solid #fecaca;border-radius:.75rem;padding:1rem 1.25rem}
.stage-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.5rem;margin-top:.75rem}
.stage-chip{font-size:.78rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;padding:.3rem .6rem;color:#475569;display:flex;align-items:center;gap:.4rem}
.stage-chip svg{flex-shrink:0}
</style>
@endpush

@section('content')
<div style="max-width:800px;margin:0 auto">

  {{-- Header --}}
  <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem">
    <a href="{{ route('admin.infrastructure.customer-deployment.installations.show', $installation) }}"
       style="display:flex;align-items:center;gap:.4rem;color:#6b7280;font-size:.9rem;text-decoration:none">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      بازگشت
    </a>
    <h1 style="font-size:1.4rem;font-weight:700;color:#0f172a;margin:0">حذف کامل مشتری</h1>
  </div>

  {{-- Alerts --}}
  @if(session('error'))
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:.5rem;padding:.75rem 1rem;margin-bottom:1rem;color:#991b1b">
      {{ session('error') }}
    </div>
  @endif

  {{-- Customer Summary --}}
  <div class="del-card">
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap">
      <div>
        <div style="font-size:.75rem;color:#94a3b8;margin-bottom:.2rem">شرکت</div>
        <div style="font-weight:600;color:#0f172a">{{ $installation->customer_company }}</div>
      </div>
      <div>
        <div style="font-size:.75rem;color:#94a3b8;margin-bottom:.2rem">دامنه</div>
        <div style="font-weight:600;color:#0f172a;direction:ltr">{{ $installation->customer_domain }}</div>
      </div>
      <div>
        <div style="font-size:.75rem;color:#94a3b8;margin-bottom:.2rem">ایمیل</div>
        <div style="color:#475569">{{ $installation->customer_email }}</div>
      </div>
      <div>
        <div style="font-size:.75rem;color:#94a3b8;margin-bottom:.2rem">وضعیت نصب</div>
        <span style="background:#f1f5f9;color:#475569;border-radius:999px;padding:.2rem .7rem;font-size:.8rem">{{ $installation->status }}</span>
      </div>
    </div>
  </div>

  {{-- Pipeline Preview --}}
  <div class="del-card">
    <h3 style="font-size:.95rem;font-weight:700;color:#0f172a;margin:0 0 .75rem 0">مراحل اجرای خودکار موتور حذف</h3>
    <div class="stage-list">
      @foreach([
        ['PRE_DELETE_AUDIT','ممیزی پیش از حذف','#6366f1'],
        ['FINAL_BACKUP','پشتیبان‌گیری نهایی','#0ea5e9'],
        ['STOP_SERVICES','توقف سرویس‌ها','#f97316'],
        ['REMOVE_QUEUE','حذف پیکربندی صف','#f97316'],
        ['REMOVE_SCHEDULER','حذف زمانبند','#f97316'],
        ['REMOVE_WEBSERVER','حذف وب‌سرور','#f97316'],
        ['REMOVE_STORAGE','حذف فضای ذخیره','#ef4444'],
        ['REMOVE_FILES','حذف فایل‌ها','#ef4444'],
        ['REMOVE_DATABASE','حذف پایگاه داده','#ef4444'],
        ['REMOVE_DATABASE_USER','حذف کاربر DB','#ef4444'],
        ['REMOVE_LICENSE','ابطال لایسنس','#8b5cf6'],
        ['REMOVE_PLATFORM_RECORDS','حذف رکوردهای پلتفرم','#8b5cf6'],
        ['REMOVE_RECOVERY_METADATA','حذف متادیتای بازیابی','#64748b'],
        ['VERIFY_DELETE','تأیید نهایی حذف','#10b981'],
      ] as [$stage, $label, $color])
      <div class="stage-chip">
        <svg width="8" height="8" viewBox="0 0 8 8" fill="{{ $color }}"><circle cx="4" cy="4" r="4"/></svg>
        {{ $label }}
      </div>
      @endforeach
    </div>
  </div>

  {{-- Form --}}
  <form method="POST" action="{{ route('admin.infrastructure.customer-deployment.installations.delete.initiate', $installation) }}" id="deleteForm">
    @csrf

    {{-- Policy Selection --}}
    <div class="del-card">
      <h3 style="font-size:.95rem;font-weight:700;color:#0f172a;margin:0 0 1rem 0">سیاست پشتیبان‌گیری</h3>

      <div style="display:grid;gap:.75rem" id="policyGrid">
        @foreach($policies as $p)
        <label class="policy-option" data-value="{{ $p['value'] }}">
          <input type="radio" name="policy" value="{{ $p['value'] }}"
                 {{ $p['value'] === 'never_delete_backup' ? 'checked' : '' }}>
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
              <div style="font-weight:600;color:#0f172a;margin-bottom:.25rem">{{ $p['label'] }}</div>
              <div style="font-size:.82rem;color:#64748b">{{ $p['description'] }}</div>
            </div>
            <div class="policy-check" style="width:20px;height:20px;border-radius:50%;border:2px solid #e2e8f0;flex-shrink:0;margin-top:.1rem;display:flex;align-items:center;justify-content:center">
              <svg class="check-icon" width="12" height="12" fill="none" stroke="#6366f1" stroke-width="3" viewBox="0 0 24 24" style="display:none"><path d="M5 13l4 4L19 7"/></svg>
            </div>
          </div>
        </label>
        @endforeach
      </div>

      @error('policy')
        <div style="color:#ef4444;font-size:.82rem;margin-top:.5rem">{{ $message }}</div>
      @enderror
    </div>

    {{-- Domain Confirmation --}}
    <div class="del-card">
      <h3 style="font-size:.95rem;font-weight:700;color:#0f172a;margin:0 0 .5rem 0">تأیید دامنه</h3>
      <p style="font-size:.85rem;color:#64748b;margin:0 0 1rem 0">
        برای تأیید، دامنه مشتری را دقیقاً تایپ کنید:
        <strong style="color:#0f172a;direction:ltr">{{ $installation->customer_domain }}</strong>
      </p>
      <input type="text"
             name="confirm_domain"
             id="confirmDomain"
             autocomplete="off"
             placeholder="{{ $installation->customer_domain }}"
             style="width:100%;padding:.6rem .9rem;border:1.5px solid #e2e8f0;border-radius:.5rem;font-size:.9rem;direction:ltr;outline:none;transition:border-color .15s"
             value="{{ old('confirm_domain') }}">
      @error('confirm_domain')
        <div style="color:#ef4444;font-size:.82rem;margin-top:.4rem">{{ $message }}</div>
      @enderror
    </div>

    {{-- Warning --}}
    <div class="del-warning" style="margin-bottom:1.5rem">
      <div style="display:flex;gap:.75rem;align-items:flex-start">
        <svg width="20" height="20" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:.1rem"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <div>
          <div style="font-weight:700;color:#991b1b;margin-bottom:.3rem">هشدار — این عملیات قابل بازگشت نیست</div>
          <div style="font-size:.85rem;color:#b91c1c">
            موتور حذف به‌صورت خودکار تمام مراحل را اجرا می‌کند:
            فایل‌ها، پایگاه داده، سرویس‌ها، لایسنس و رکوردهای پلتفرم حذف می‌شوند.
          </div>
        </div>
      </div>
    </div>

    {{-- Submit --}}
    <div style="display:flex;gap:1rem">
      <button type="submit" id="submitBtn"
              disabled
              style="background:#ef4444;color:#fff;border:none;border-radius:.5rem;padding:.7rem 1.5rem;font-size:.9rem;font-weight:600;cursor:not-allowed;opacity:.5;transition:all .2s">
        حذف کامل مشتری
      </button>
      <a href="{{ route('admin.infrastructure.customer-deployment.installations.show', $installation) }}"
         style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;padding:.7rem 1.25rem;font-size:.9rem;color:#475569;text-decoration:none;display:inline-flex;align-items:center">
        انصراف
      </a>
    </div>
  </form>
</div>

<script>
// Policy selection
document.querySelectorAll('.policy-option').forEach(function(el) {
  el.addEventListener('click', function() {
    document.querySelectorAll('.policy-option').forEach(function(o) {
      o.classList.remove('selected');
      o.querySelector('.check-icon').style.display = 'none';
      o.querySelector('.policy-check').style.borderColor = '#e2e8f0';
    });
    this.classList.add('selected');
    this.querySelector('input').checked = true;
    this.querySelector('.check-icon').style.display = 'block';
    this.querySelector('.policy-check').style.borderColor = '#6366f1';
  });
});

// Initialise the checked option
var initialPolicy = document.querySelector('input[name=policy]:checked');
if (initialPolicy) {
  var lbl = initialPolicy.closest('.policy-option');
  lbl.classList.add('selected');
  lbl.querySelector('.check-icon').style.display = 'block';
  lbl.querySelector('.policy-check').style.borderColor = '#6366f1';
}

// Domain confirmation activates submit button
var expectedDomain = '{{ $installation->customer_domain }}';
var domainInput    = document.getElementById('confirmDomain');
var submitBtn      = document.getElementById('submitBtn');

domainInput.addEventListener('input', function() {
  var match = this.value.trim() === expectedDomain;
  submitBtn.disabled = !match;
  submitBtn.style.opacity   = match ? '1' : '0.5';
  submitBtn.style.cursor    = match ? 'pointer' : 'not-allowed';
  this.style.borderColor    = this.value === '' ? '#e2e8f0' : (match ? '#10b981' : '#ef4444');
});
</script>
@endsection
