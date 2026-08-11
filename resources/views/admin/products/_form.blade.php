<div class="mb-3">
    <label class="form-label">SKU</label>
    <input type="text" name="sku" class="form-control" value="{{ old('sku', $product->sku ?? '') }}" required>
</div>
<div class="mb-3">
    <label class="form-label">نام (فارسی)</label>
    <input type="text" name="name_fa" class="form-control" value="{{ old('name_fa', $product->name_fa ?? '') }}" required>
</div>
<div class="mb-3">
    <label class="form-label">نام (انگلیسی)</label>
    <input type="text" name="name_en" class="form-control" value="{{ old('name_en', $product->name_en ?? '') }}">
</div>
<div class="mb-3">
    <label class="form-label">نوع</label>
    <select name="type" class="form-select" required>
        <option value="license_template" {{ old('type', isset($product) ? $product->type : ($preselectedModuleId ? 'module_addon' : '')) === 'license_template' ? 'selected' : '' }}>لایسنس قالب سایت</option>
        <option value="module_addon" {{ old('type', isset($product) ? $product->type : ($preselectedModuleId ? 'module_addon' : '')) === 'module_addon' ? 'selected' : '' }}>ماژول افزودنی</option>
        <option value="support_plan" {{ old('type', isset($product) ? $product->type : ($preselectedModuleId ? 'module_addon' : '')) === 'support_plan' ? 'selected' : '' }}>پلن پشتیبانی</option>
        <option value="motion_asset" {{ old('type', isset($product) ? $product->type : '') === 'motion_asset' ? 'selected' : '' }}>موشن سفارشی (Bespoke)</option>
    </select>
</div>
<div class="mb-3">
    <label class="form-label">قیمت (ریال)</label>
    <input type="number" name="price_irr" class="form-control" value="{{ old('price_irr', $product->price_irr ?? 0) }}" min="0" required>
</div>
<div class="mb-3">
    <label class="form-label">ماژول (برای نوع ماژول افزودنی)</label>
    <select name="module_id" class="form-select">
        <option value="">—</option>
        @foreach($modules as $m)
            <option value="{{ $m->id }}" {{ old('module_id', isset($product) ? $product->module_id : ($preselectedModuleId ?? '')) == $m->id ? 'selected' : '' }}>{{ $m->title }}</option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label class="form-label">پلن پشتیبانی (برای نوع پشتیبانی)</label>
    <select name="support_plan_id" class="form-select">
        <option value="">—</option>
        @foreach($supportPlans as $sp)
            <option value="{{ $sp->id }}" {{ old('support_plan_id', $product->support_plan_id ?? '') == $sp->id ? 'selected' : '' }}>{{ $sp->title }}</option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label class="form-label">یک‌بار خرید</label>
    <input type="checkbox" name="is_one_time" value="1" {{ old('is_one_time', $product->is_one_time ?? true) ? 'checked' : '' }}>
</div>
<div class="mb-3">
    <label class="form-label">فعال</label>
    <input type="checkbox" name="is_active" value="1" {{ old('is_active', isset($product) ? $product->is_active : true) ? 'checked' : '' }}>
</div>
<div class="mb-3">
    <label class="form-label">توضیح</label>
    <textarea name="description" class="form-control" rows="2">{{ old('description', $product->description ?? '') }}</textarea>
</div>
