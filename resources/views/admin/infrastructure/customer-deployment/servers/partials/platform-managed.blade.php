{{-- Phase 4: server ownership. Only platform-owned servers receive the tenant media (object storage) key. --}}
<div class="mt-4 border border-amber-200 bg-amber-50 rounded-lg p-3" data-testid="platform-managed-field">
    <input type="hidden" name="is_platform_managed" value="0">
    <label class="flex items-start gap-2 text-sm text-gray-800 cursor-pointer">
        <input type="checkbox" name="is_platform_managed" value="1" class="mt-1" @checked($checked)>
        <span>
            <strong>سرور متعلق به پلتفرم است (مدیریت‌شده توسط ما)</strong>
            <span class="block text-xs text-gray-600 leading-6 mt-1">
                فقط برای سرورهای خودمان فعال کنید. سایت‌های این سرور فایل‌های آپلودی را در فضای ابری آروان (باکت مدیا) ذخیره می‌کنند و کلید دسترسی باکت مدیا در فایل .env آن‌ها نوشته می‌شود.
                روی سروری که متعلق به مشتری است هرگز فعال نکنید؛ فایل‌های آن سایت‌ها روی دیسک همان سرور می‌مانند.
            </span>
        </span>
    </label>
</div>
