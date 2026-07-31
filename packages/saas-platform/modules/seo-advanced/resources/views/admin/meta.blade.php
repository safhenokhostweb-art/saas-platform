@extends(config('seo-advanced.layouts.admin'))

@section('title', 'تنظیمات SEO')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">تنظیمات SEO — سایت #{{ $siteId }}</h1>

    <form method="POST" action="{{ route('seo-advanced.admin.meta.update', $siteId) }}">
        @csrf
        @method('PUT')

        <fieldset class="mb-4">
            <legend class="h5">Meta Management</legend>
            <div class="mb-3">
                <label class="form-label">عنوان متا (Meta Title)</label>
                <input type="text" name="meta_title" maxlength="255" class="form-control" value="{{ old('meta_title', $settings->meta_title ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">توضیحات متا (Meta Description)</label>
                <textarea name="meta_description" maxlength="500" class="form-control">{{ old('meta_description', $settings->meta_description ?? '') }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">کلمات کلیدی (Meta Keywords)</label>
                <input type="text" name="meta_keywords" maxlength="500" class="form-control" value="{{ old('meta_keywords', $settings->meta_keywords ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Robots</label>
                <select name="robots_directive" class="form-select">
                    @foreach (['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'] as $option)
                        <option value="{{ $option }}" @selected(old('robots_directive', $settings->robots_directive ?? 'index,follow') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Canonical URL</label>
                <input type="text" name="canonical_url" maxlength="500" class="form-control" value="{{ old('canonical_url', $settings->canonical_url ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">سیاست Canonical (Canonical Policy)</label>
                <select name="canonical_strategy" class="form-select">
                    <option value="auto" @selected(old('canonical_strategy', $settings->canonical_strategy ?? 'auto') === 'auto')>خودکار (آدرس همان صفحه)</option>
                    <option value="force_https" @selected(old('canonical_strategy', $settings->canonical_strategy ?? '') === 'force_https')>اجبار به HTTPS</option>
                    <option value="custom" @selected(old('canonical_strategy', $settings->canonical_strategy ?? '') === 'custom')>آدرس اختصاصی بالا (Canonical URL)</option>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">حداقل طول عنوان (Title)</label>
                    <input type="number" name="title_min_length" class="form-control" value="{{ old('title_min_length', $settings->title_min_length ?? '') }}" placeholder="پیش‌فرض: ۳۰">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">حداکثر طول عنوان (Title)</label>
                    <input type="number" name="title_max_length" class="form-control" value="{{ old('title_max_length', $settings->title_max_length ?? '') }}" placeholder="پیش‌فرض: ۶۰">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">حداقل طول توضیحات (Description)</label>
                    <input type="number" name="description_min_length" class="form-control" value="{{ old('description_min_length', $settings->description_min_length ?? '') }}" placeholder="پیش‌فرض: ۷۰">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">حداکثر طول توضیحات (Description)</label>
                    <input type="number" name="description_max_length" class="form-control" value="{{ old('description_max_length', $settings->description_max_length ?? '') }}" placeholder="پیش‌فرض: ۱۶۰">
                </div>
            </div>
        </fieldset>

        <fieldset class="mb-4">
            <legend class="h5">OpenGraph / Twitter Card</legend>
            <div class="mb-3">
                <label class="form-label">OG Title</label>
                <input type="text" name="og_title" maxlength="255" class="form-control" value="{{ old('og_title', $settings->og_title ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">OG Description</label>
                <textarea name="og_description" maxlength="500" class="form-control">{{ old('og_description', $settings->og_description ?? '') }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">OG Image URL</label>
                <input type="text" name="og_image_url" maxlength="500" class="form-control" value="{{ old('og_image_url', $settings->og_image_url ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">نوع Twitter Card</label>
                <select name="twitter_card_type" class="form-select">
                    <option value="summary" @selected(old('twitter_card_type', $settings->twitter_card_type ?? 'summary') === 'summary')>Summary</option>
                    <option value="summary_large_image" @selected(old('twitter_card_type', $settings->twitter_card_type ?? '') === 'summary_large_image')>Summary Large Image</option>
                </select>
            </div>
        </fieldset>

        <fieldset class="mb-4">
            <legend class="h5">Schema.org</legend>
            <div class="mb-3">
                <label class="form-label">نوع Schema</label>
                <select name="schema_type" class="form-select">
                    <option value="website" @selected(old('schema_type', $settings->schema_type ?? 'website') === 'website')>Website</option>
                    <option value="organization" @selected(old('schema_type', $settings->schema_type ?? '') === 'organization')>Organization</option>
                    <option value="local_business" @selected(old('schema_type', $settings->schema_type ?? '') === 'local_business')>LocalBusiness</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">نام سازمان</label>
                <input type="text" name="organization_name" maxlength="255" class="form-control" value="{{ old('organization_name', $settings->organization_name ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">لوگو سازمان (URL)</label>
                <input type="text" name="organization_logo_url" maxlength="500" class="form-control" value="{{ old('organization_logo_url', $settings->organization_logo_url ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">آدرس کسب‌وکار</label>
                <input type="text" name="business_address" maxlength="500" class="form-control" value="{{ old('business_address', $settings->business_address ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">تلفن کسب‌وکار</label>
                <input type="text" name="business_phone" maxlength="64" class="form-control" value="{{ old('business_phone', $settings->business_phone ?? '') }}">
            </div>
        </fieldset>

        <button type="submit" class="btn btn-primary">ذخیره</button>
    </form>
</div>
@endsection
