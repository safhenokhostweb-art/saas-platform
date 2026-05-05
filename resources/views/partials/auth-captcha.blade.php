@if(!empty($captcha['enabled'] ?? false))
<div>
    <label class="form-label">کپچا</label>
    <p class="text-gray-500 text-xs mb-1">در صورت عدم نمایش کپچا یا خطای «منقضی شده»، صفحه را بروز (رفرش) کنید و از همان آدرس دامنهٔ ورود استفاده کنید.</p>
    @if(($captcha['type'] ?? '') === 'persian_number' && !empty($captcha['persian_code']))
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-2xl font-bold tracking-widest text-gray-700 bg-gray-100 px-3 py-2 rounded select-none" aria-hidden="true">{{ $captcha['persian_code'] }}</span>
            <input type="text" name="captcha_answer" class="form-control w-32" placeholder="عدد بالا را وارد کنید" maxlength="6" required autocomplete="off">
        </div>
    @elseif(($captcha['type'] ?? '') === 'normal' && !empty($captcha['normal_text']))
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xl font-bold text-gray-700 bg-gray-100 px-3 py-2 rounded select-none" aria-hidden="true">{{ $captcha['normal_text'] }}</span>
            <input type="text" name="captcha_answer" class="form-control w-24" placeholder="جواب" maxlength="4" required autocomplete="off">
        </div>
    @elseif(($captcha['type'] ?? '') === 'hcaptcha' && !empty($captcha['hcaptcha_site_key']))
        <div class="h-captcha" data-sitekey="{{ $captcha['hcaptcha_site_key'] }}" data-hl="fa"></div>
    @endif
    @error('captcha')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>
@endif
