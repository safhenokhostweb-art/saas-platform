<div>
    <label class="form-label">شناسه دارایی‌های الزامی (با کاما جدا کنید)</label>
    <input type="text" name="required_asset_ids" class="form-control" placeholder="motion-1, motion-2" required>
</div>
<div>
    <label class="form-label">شناسه دارایی‌های اختیاری (اختیاری)</label>
    <input type="text" name="optional_asset_ids" class="form-control" placeholder="motion-3">
</div>
<div class="grid grid-cols-2 gap-2">
    <div>
        <label class="form-label">نوع دارایی</label>
        <input type="text" name="compatibility_asset_type" class="form-control" placeholder="lottie">
    </div>
    <div>
        <label class="form-label">بخش</label>
        <input type="text" name="compatibility_section" class="form-control" placeholder="hero">
    </div>
</div>
<div class="grid grid-cols-2 gap-2">
    <div>
        <label class="form-label">صنعت</label>
        <input type="text" name="compatibility_industry" class="form-control">
    </div>
    <label class="flex items-center gap-2 mt-6 text-sm text-gray-600">
        <input type="checkbox" name="compatibility_theme_tokens_required" value="1" class="form-check-input">
        نیاز به theme tokens
    </label>
</div>
