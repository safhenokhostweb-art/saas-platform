@php $n = $notification ?? null; @endphp
<div class="mb-3">
    <label class="form-label">عنوان *</label>
    <input type="text" name="title" class="form-control" value="{{ old('title', $n?->title) }}" required placeholder="مثلاً اطلاعیه مهم">
</div>
<div class="mb-3">
    <label class="form-label">متن</label>
    <textarea name="body" class="form-control" rows="4" placeholder="متن کامل اعلان...">{{ old('body', $n?->body) }}</textarea>
</div>
<div class="mb-3">
    <label class="form-label">نوع</label>
    <select name="type" class="form-select">
        <option value="info" {{ old('type', $n?->type ?? 'info') === 'info' ? 'selected' : '' }}>اطلاع</option>
        <option value="success" {{ old('type', $n?->type) === 'success' ? 'selected' : '' }}>موفق</option>
        <option value="warning" {{ old('type', $n?->type) === 'warning' ? 'selected' : '' }}>هشدار</option>
        <option value="danger" {{ old('type', $n?->type) === 'danger' ? 'selected' : '' }}>مهم</option>
    </select>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">لینک</label>
        <input type="url" name="link" class="form-control" value="{{ old('link', $n?->link) }}" placeholder="https://">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">متن لینک</label>
        <input type="text" name="link_text" class="form-control" value="{{ old('link_text', $n?->link_text) }}" placeholder="مشاهده بیشتر">
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">مخاطب</label>
        <select name="audience" class="form-select">
            <option value="admin" {{ old('audience', $n?->audience ?? 'admin') === 'admin' ? 'selected' : '' }}>پنل ادمین سایت مشتری (تولبار شناور)</option>
            <option value="public" {{ old('audience', $n?->audience) === 'public' ? 'selected' : '' }}>کاربران نهایی (فرانت و تولبار «حساب من»)</option>
        </select>
        <small class="text-muted">«ادمین» فقط در پنل مدیریت مشتری دیده می‌شود. «کاربران نهایی» در سایت عمومی (ویجت اعلان) و فقط برای کاربران لاگین‌کرده در نوار حساب کاربری نمایش داده می‌شود — مشروط به فعال بودن ماژول اعلان در آن سایت.</small>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">اولویت</label>
        <select name="priority" class="form-select">
            <option value="low" {{ old('priority', $n?->priority ?? 'normal') === 'low' ? 'selected' : '' }}>کم</option>
            <option value="normal" {{ old('priority', $n?->priority ?? 'normal') === 'normal' ? 'selected' : '' }}>عادی</option>
            <option value="high" {{ old('priority', $n?->priority ?? 'normal') === 'high' ? 'selected' : '' }}>مهم</option>
            <option value="urgent" {{ old('priority', $n?->priority ?? 'normal') === 'urgent' ? 'selected' : '' }}>فوری</option>
        </select>
        <small class="text-muted">اعلان‌های فوری در صدر لیست نمایش داده می‌شوند.</small>
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">موقعیت تولبار</label>
        <select name="position" class="form-select">
            <option value="top" {{ old('position', $n?->position ?? 'top') === 'top' ? 'selected' : '' }}>بالا</option>
            <option value="left" {{ old('position', $n?->position) === 'left' ? 'selected' : '' }}>چپ</option>
            <option value="right" {{ old('position', $n?->position) === 'right' ? 'selected' : '' }}>راست</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">موقعیت تصویر</label>
        <select name="image_position" class="form-select">
            <option value="background" {{ old('image_position', $n?->image_position ?? 'right') === 'background' ? 'selected' : '' }}>پس‌زمینه</option>
            <option value="left" {{ old('image_position', $n?->image_position) === 'left' ? 'selected' : '' }}>چپ</option>
            <option value="right" {{ old('image_position', $n?->image_position) === 'right' ? 'selected' : '' }}>راست</option>
        </select>
    </div>
</div>
<div class="mb-3">
    <label class="form-label">تصویر (پس‌زمینه یا کنار اعلان)</label>
    <div class="d-flex gap-2 align-items-center">
        <input type="hidden" name="image_path" id="notif-image-path" value="{{ old('image_path', $n?->image_path) }}">
        <input type="file" id="notif-image-file" accept="image/*" class="d-none">
        <button type="button" id="notif-image-upload-btn" class="btn btn-outline-secondary">انتخاب تصویر</button>
        <span class="text-sm text-gray-500" id="notif-image-preview">{{ $n && $n->image_path ? '✓ ذخیره شد: ' . $n->image_path : '' }}</span>
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">تاریخ شروع (شمسی)</label>
        <x-persian-date-input name="starts_at" :value="old('starts_at', $n ? \App\Helpers\Jalali::format($n->starts_at) : '')" placeholder="۱۴۰۴/۱۲/۰۵" class="form-control" />
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">تاریخ انقضا (شمسی)</label>
        <x-persian-date-input name="expires_at" :value="old('expires_at', $n ? \App\Helpers\Jalali::format($n->expires_at) : '')" placeholder="۱۴۰۴/۱۲/۳۰" class="form-control" />
    </div>
</div>
<small class="text-muted d-block mb-2">خالی = نمایش فوری و بدون انقضا. اگر تاریخ شروع در آینده باشد، اعلان تا آن زمان نمایش داده نمی‌شود.</small>
<div class="mb-3">
    <label class="inline-flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $n?->is_active ?? true) ? 'checked' : '' }} class="rounded">
        <span>اعلان فعال است</span>
    </label>
</div>
