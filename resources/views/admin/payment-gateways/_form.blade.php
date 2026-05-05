@php
    $gw = $paymentGateway ?? new \App\Models\PaymentGateway();
    $config = $gw->config ?? [];
@endphp
<div class="mb-3">
    <label class="form-label">نام نمایشی</label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $gw->name) }}" placeholder="مثلاً زرین‌پال اصلی" required>
</div>
<div class="mb-3">
    <label class="form-label">نوع درگاه</label>
    <select name="driver" class="form-select" required>
        @foreach(\App\Models\PaymentGateway::drivers() as $key => $label)
            <option value="{{ $key }}" {{ old('driver', $gw->driver) === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="mb-3 p-3 bg-gray-50 rounded border" id="gateway-config-fields">
    <h6 class="font-bold mb-2">تنظیمات درگاه</h6>
    @php $currentDriver = old('driver', $gw->driver ?? 'zarinpal'); @endphp
    <div class="mb-2 gateway-field" data-driver="zarinpal">
        <label class="form-label">مرچنت کد (زرین‌پال)</label>
        <input type="text" name="merchant_id" class="form-control" value="{{ old('merchant_id', $config['merchant_id'] ?? '') }}" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
    </div>
    <div class="mb-2 gateway-field" data-driver="idpay">
        <label class="form-label">API Key (آی‌دی‌پی)</label>
        <input type="text" name="api_key" class="form-control" value="{{ old('api_key', $config['api_key'] ?? '') }}" placeholder="کلید API از پنل آی‌دی‌پی">
    </div>
    <div class="mb-2">
        <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="sandbox" value="1" {{ old('sandbox', $config['sandbox'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300">
            <span>حالت تست (سندباکس)</span>
        </label>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var driverSelect = document.querySelector('select[name=driver]');
    var fields = document.querySelectorAll('.gateway-field');
    function toggle() {
        var d = driverSelect?.value || 'zarinpal';
        fields.forEach(function(el) {
            el.style.display = el.dataset.driver === d ? 'block' : 'none';
        });
    }
    if (driverSelect) { driverSelect.addEventListener('change', toggle); toggle(); }
});
</script>
@endpush

<div class="mb-3">
    <label class="inline-flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $gw->is_active ?? true) ? 'checked' : '' }} class="rounded border-gray-300">
        <span>فعال</span>
    </label>
</div>
<div class="mb-3">
    <label class="inline-flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_default" value="1" {{ old('is_default', $gw->is_default ?? false) ? 'checked' : '' }} class="rounded border-gray-300">
        <span>درگاه پیش‌فرض پلتفرم</span>
    </label>
    <p class="text-sm text-gray-500 mt-1">اگر هیچ درگاهی به سایت تخصیص نشده باشد، از این درگاه استفاده می‌شود.</p>
</div>
