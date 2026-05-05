<?php $__env->startSection('header', 'تنظیمات عمومی پلتفرم'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('admin.platform-settings.guide', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<div class="max-w-4xl space-y-8">

    <form method="POST" action="<?php echo e(route('admin.platform-settings.update')); ?>" enctype="multipart/form-data" class="space-y-8">
        <?php echo csrf_field(); ?>

        
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="font-bold text-lg mb-4 border-b border-gray-200 pb-2">عمومی</h2>
            <div class="space-y-4">
                <div>
                    <label class="form-label">نام پلتفرم</label>
                    <input type="text" name="platform_name" class="form-control w-full max-w-md" value="<?php echo e(old('platform_name', $general['platform_name'] ?? '')); ?>" placeholder="پلتفرم SaaS">
                </div>
                <div>
                    <label class="form-label">لوگو</label>
                    <?php if(\App\Helpers\PlatformSettings::logoUrl()): ?>
                        <div class="mb-2"><img src="<?php echo e(\App\Helpers\PlatformSettings::logoUrl()); ?>" alt="لوگو" class="h-12 object-contain"></div>
                    <?php endif; ?>
                    <input type="file" name="platform_logo" class="form-control w-full max-w-md" accept="image/*">
                    <input type="text" name="platform_logo_path" class="form-control w-full max-w-md mt-1" placeholder="یا مسیر پروژه (platform/logo.png) یا آدرس کامل هاست (https://...)" value="<?php echo e(old('platform_logo_path', \App\Helpers\PlatformSettings::get('platform_logo', ''))); ?>">
                    <p class="text-gray-500 text-xs mt-1">حداکثر ۲ مگابایت برای آپلود. یا مسیر نسبی (مثلاً <code class="bg-gray-100 px-1 rounded">platform/logo.png</code>) یا آدرس کامل تصویر روی هاست (مثلاً <code class="bg-gray-100 px-1 rounded">https://سایت شما/storage/logo.png</code>).</p>
                </div>
                <div>
                    <label class="form-label">ایمیل تماس</label>
                    <input type="email" name="contact_email" class="form-control w-full max-w-md" value="<?php echo e(old('contact_email', $general['contact_email'] ?? '')); ?>" placeholder="info@example.com">
                </div>
                <div>
                    <label class="form-label">تلفن تماس</label>
                    <input type="text" name="contact_phone" class="form-control w-full max-w-md" value="<?php echo e(old('contact_phone', $general['contact_phone'] ?? '')); ?>" placeholder="۰۲۱۱۲۳۴۵۶۷۸">
                </div>
                <div>
                    <label class="form-label">آدرس</label>
                    <textarea name="contact_address" class="form-control w-full max-w-md" rows="2" placeholder="آدرس دفتر یا پشتیبانی"><?php echo e(old('contact_address', $general['contact_address'] ?? '')); ?></textarea>
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="registration_open" value="1" <?php echo e(old('registration_open', $general['registration_open'] ?? '1') === '1' ? 'checked' : ''); ?> class="rounded border-gray-300">
                        <span>ثبت‌نام باز است (کاربران جدید بتوانند ثبت‌نام کنند)</span>
                    </label>
                </div>
            </div>
        </div>

        
        <?php $colorPalette = ['#4f46e5','#2563eb','#059669','#dc2626','#ea580c','#ca8a04','#7c3aed','#db2777','#0d9488','#1e40af','#374151','#ffffff','#f9fafb','#eff6ff','#111827']; ?>
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="font-bold text-lg mb-4 border-b border-gray-200 pb-2">پالت رنگ و ظاهر</h2>
            <p class="text-gray-500 text-sm mb-4">رنگ‌های منوها، عناوین، لینک‌ها و دکمه‌ها در کل سایت را تنظیم کنید. مقدار را به صورت هگز (مثلاً #4f46e5) وارد کنید.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php $__currentLoopData = \App\Helpers\PlatformSettings::THEME_KEYS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $default): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $label = str_replace('theme_', '', $key);
                        $label = str_replace('_', ' ', $label);
                    ?>
                    <div>
                        <label class="form-label text-sm"><?php echo e($label); ?></label>
                        <div class="flex items-center gap-2">
                            <input type="color" id="color_<?php echo e($key); ?>" value="<?php echo e(old($key, $theme[$key] ?? $default)); ?>" class="h-10 w-14 rounded border cursor-pointer" title="<?php echo e($key); ?>">
                            <input type="text" name="<?php echo e($key); ?>" class="form-control flex-1" value="<?php echo e(old($key, $theme[$key] ?? $default)); ?>" placeholder="<?php echo e($default); ?>">
                        </div>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <?php $__currentLoopData = $colorPalette; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <button type="button" class="w-5 h-5 rounded border border-gray-200 hover:scale-110 transition" style="background:<?php echo e($c); ?>" title="<?php echo e($c); ?>" onclick="var d=this.closest('div').previousElementSibling; var col=d.querySelector('input[type=color]'); var inp=d.querySelector('input[type=text]'); if(col){col.value='<?php echo e($c); ?>';} if(inp){inp.value='<?php echo e($c); ?>';}"></button>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="font-bold text-lg mb-4 border-b border-gray-200 pb-2">اعلان خودکار انقضا</h2>
            <p class="text-gray-500 text-sm mb-4">برای هر سایت، یک ماه قبل از انقضا و سپس هر ۱۰ روز یک‌بار تا زمان انقضا، هشدار در <strong>ادمین اصلی پلتفرم (همین پنل)</strong> ارسال می‌شود و در داشبورد پلتفرم نمایش داده می‌شود. تاریخ انقضای پشتیبانی در ویرایش سایت و تاریخ انقضای هاست و دامنه در بخش «اطلاعات هاست و دیتابیس» همان صفحه ثبت می‌شود.</p>
            <div class="space-y-3">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="auto_notify_support_expiry" value="1" <?php echo e(old('auto_notify_support_expiry', $autoExpiry['auto_notify_support_expiry'] ?? '1') === '1' ? 'checked' : ''); ?> class="rounded border-gray-300">
                    <span>ارسال خودکار هشدار انقضای پشتیبانی</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="auto_notify_host_expiry" value="1" <?php echo e(old('auto_notify_host_expiry', $autoExpiry['auto_notify_host_expiry'] ?? '1') === '1' ? 'checked' : ''); ?> class="rounded border-gray-300">
                    <span>ارسال خودکار هشدار انقضای هاست</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="auto_notify_domain_expiry" value="1" <?php echo e(old('auto_notify_domain_expiry', $autoExpiry['auto_notify_domain_expiry'] ?? '1') === '1' ? 'checked' : ''); ?> class="rounded border-gray-300">
                    <span>ارسال خودکار هشدار انقضای دامنه</span>
                </label>
            </div>

            <h3 class="font-semibold mt-4 mb-2 pt-4 border-t border-gray-100">پیامک همراه اعلان انقضا</h3>
            <p class="text-gray-500 text-sm mb-3">با ارسال هر اعلان انقضا، به‌صورت اختیاری پیامک نیز ارسال می‌شود. ادمین سایت مشتری (شماره موبایل مشتری در پروندهٔ مشتری) در صورت فعال بودن پیامک دریافت می‌کند. ارسال به ادمین پلتفرم را می‌توانید جداگانه غیرفعال کنید. قالب پیامک و تنظیمات سرویس‌هایی مثل ملی‌پیامک در <code class="bg-gray-100 px-1 rounded">.env</code> و فایل <code class="bg-gray-100 px-1 rounded">config/sms.php</code> است؛ راهنمای کامل قالب در <a href="<?php echo e(url('docs/SMS-TEMPLATE-EXPIRY.md')); ?>" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">docs/SMS-TEMPLATE-EXPIRY.md</a>.</p>
            <div class="space-y-3">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="sms_expiry_enabled" value="1" <?php echo e(old('sms_expiry_enabled', $smsExpiry['sms_expiry_enabled'] ?? '1') === '1' ? 'checked' : ''); ?> class="rounded border-gray-300">
                    <span>ارسال پیامک همراه اعلان انقضا (به ادمین سایت مشتری)</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="expiry_sms_to_platform_admin" value="1" <?php echo e(old('expiry_sms_to_platform_admin', $smsExpiry['expiry_sms_to_platform_admin'] ?? '0') === '1' ? 'checked' : ''); ?> class="rounded border-gray-300">
                    <span>ارسال پیامک به ادمین پلتفرم (کاربران ادمین با شماره موبایل)</span>
                </label>
            </div>
        </div>

        
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="font-bold text-lg mb-4 border-b border-gray-200 pb-2">ویجت راهنما</h2>
            <p class="text-gray-500 text-sm mb-4">دکمهٔ شناور راهنما و پنل جستجو در همان صفحه: فعال/غیرفعال، موقعیت و پالت رنگ را تنظیم کنید.</p>
            <div class="space-y-4">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="guide_widget_enabled" value="1" <?php echo e(old('guide_widget_enabled', $guideWidget['guide_widget_enabled'] ?? '1') === '1' ? 'checked' : ''); ?> class="rounded border-gray-300">
                    <span>ویجت راهنما فعال باشد</span>
                </label>
                <div>
                    <label class="form-label">موقعیت دکمهٔ ویجت</label>
                    <select name="guide_widget_position" class="form-control w-full max-w-xs">
                        <?php $__currentLoopData = ['bottom-right' => 'پایین راست', 'bottom-left' => 'پایین چپ', 'top-right' => 'بالا راست', 'top-left' => 'بالا چپ']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($val); ?>" <?php echo e(old('guide_widget_position', $guideWidget['guide_widget_position'] ?? 'bottom-right') === $val ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <?php $guidePalette = ['#4f46e5','#2563eb','#059669','#dc2626','#ffffff','#f9fafb','#111827','#374151','#eff6ff','#7c3aed','#db2777','#0d9488']; ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                    <div>
                        <label class="form-label text-sm">رنگ پس‌زمینه دکمه</label>
                        <div class="flex items-center gap-2">
                            <input type="color" id="color_guide_widget_btn_bg" value="<?php echo e(old('guide_widget_btn_bg', $guideWidget['guide_widget_btn_bg'] ?? '#2563eb')); ?>" class="h-10 w-14 rounded border cursor-pointer">
                            <input type="text" name="guide_widget_btn_bg" class="form-control flex-1" value="<?php echo e(old('guide_widget_btn_bg', $guideWidget['guide_widget_btn_bg'] ?? '#2563eb')); ?>" placeholder="#2563eb">
                        </div>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <?php $__currentLoopData = $guidePalette; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <button type="button" class="w-5 h-5 rounded border border-gray-200 hover:scale-110 transition" style="background:<?php echo e($c); ?>" title="<?php echo e($c); ?>" onclick="var d=this.closest('div').previousElementSibling; var col=d.querySelector('input[type=color]'); var inp=d.querySelector('input[type=text]'); if(col)col.value='<?php echo e($c); ?>'; if(inp)inp.value='<?php echo e($c); ?>';"></button>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                    <div>
                        <label class="form-label text-sm">رنگ متن دکمه</label>
                        <div class="flex items-center gap-2">
                            <input type="color" id="color_guide_widget_btn_text" value="<?php echo e(old('guide_widget_btn_text', $guideWidget['guide_widget_btn_text'] ?? '#ffffff')); ?>" class="h-10 w-14 rounded border cursor-pointer">
                            <input type="text" name="guide_widget_btn_text" class="form-control flex-1" value="<?php echo e(old('guide_widget_btn_text', $guideWidget['guide_widget_btn_text'] ?? '#ffffff')); ?>" placeholder="#ffffff">
                        </div>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <?php $__currentLoopData = $guidePalette; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <button type="button" class="w-5 h-5 rounded border border-gray-200 hover:scale-110 transition" style="background:<?php echo e($c); ?>" title="<?php echo e($c); ?>" onclick="var d=this.closest('div').previousElementSibling; var col=d.querySelector('input[type=color]'); var inp=d.querySelector('input[type=text]'); if(col)col.value='<?php echo e($c); ?>'; if(inp)inp.value='<?php echo e($c); ?>';"></button>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                    <div>
                        <label class="form-label text-sm">رنگ پس‌زمینه پنل</label>
                        <div class="flex items-center gap-2">
                            <input type="color" id="color_guide_widget_panel_bg" value="<?php echo e(old('guide_widget_panel_bg', $guideWidget['guide_widget_panel_bg'] ?? '#ffffff')); ?>" class="h-10 w-14 rounded border cursor-pointer">
                            <input type="text" name="guide_widget_panel_bg" class="form-control flex-1" value="<?php echo e(old('guide_widget_panel_bg', $guideWidget['guide_widget_panel_bg'] ?? '#ffffff')); ?>" placeholder="#ffffff">
                        </div>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <?php $__currentLoopData = $guidePalette; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <button type="button" class="w-5 h-5 rounded border border-gray-200 hover:scale-110 transition" style="background:<?php echo e($c); ?>" title="<?php echo e($c); ?>" onclick="var d=this.closest('div').previousElementSibling; var col=d.querySelector('input[type=color]'); var inp=d.querySelector('input[type=text]'); if(col)col.value='<?php echo e($c); ?>'; if(inp)inp.value='<?php echo e($c); ?>';"></button>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                    <div>
                        <label class="form-label text-sm">رنگ هدر پنل</label>
                        <div class="flex items-center gap-2">
                            <input type="color" id="color_guide_widget_panel_header_bg" value="<?php echo e(old('guide_widget_panel_header_bg', $guideWidget['guide_widget_panel_header_bg'] ?? '#f9fafb')); ?>" class="h-10 w-14 rounded border cursor-pointer">
                            <input type="text" name="guide_widget_panel_header_bg" class="form-control flex-1" value="<?php echo e(old('guide_widget_panel_header_bg', $guideWidget['guide_widget_panel_header_bg'] ?? '#f9fafb')); ?>" placeholder="#f9fafb">
                        </div>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <?php $__currentLoopData = $guidePalette; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <button type="button" class="w-5 h-5 rounded border border-gray-200 hover:scale-110 transition" style="background:<?php echo e($c); ?>" title="<?php echo e($c); ?>" onclick="var d=this.closest('div').previousElementSibling; var col=d.querySelector('input[type=color]'); var inp=d.querySelector('input[type=text]'); if(col)col.value='<?php echo e($c); ?>'; if(inp)inp.value='<?php echo e($c); ?>';"></button>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                    <div>
                        <label class="form-label text-sm">رنگ متن پنل</label>
                        <div class="flex items-center gap-2">
                            <input type="color" id="color_guide_widget_panel_text" value="<?php echo e(old('guide_widget_panel_text', $guideWidget['guide_widget_panel_text'] ?? '#111827')); ?>" class="h-10 w-14 rounded border cursor-pointer">
                            <input type="text" name="guide_widget_panel_text" class="form-control flex-1" value="<?php echo e(old('guide_widget_panel_text', $guideWidget['guide_widget_panel_text'] ?? '#111827')); ?>" placeholder="#111827">
                        </div>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <?php $__currentLoopData = $guidePalette; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <button type="button" class="w-5 h-5 rounded border border-gray-200 hover:scale-110 transition" style="background:<?php echo e($c); ?>" title="<?php echo e($c); ?>" onclick="var d=this.closest('div').previousElementSibling; var col=d.querySelector('input[type=color]'); var inp=d.querySelector('input[type=text]'); if(col)col.value='<?php echo e($c); ?>'; if(inp)inp.value='<?php echo e($c); ?>';"></button>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-100">
                    <label class="form-label">موقعیت ویجت اعلان ادمین</label>
                    <p class="text-gray-500 text-xs mb-2">دکمهٔ زنگولهٔ اعلان‌ها در پنل مدیریت سایت مشتری کجا نمایش داده شود.</p>
                    <select name="admin_notif_widget_position" class="form-control w-full max-w-xs">
                        <?php $__currentLoopData = ['bottom-right' => 'پایین راست', 'bottom-left' => 'پایین چپ', 'top-right' => 'بالا راست', 'top-left' => 'بالا چپ']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($val); ?>" <?php echo e(old('admin_notif_widget_position', $guideWidget['admin_notif_widget_position'] ?? 'bottom-right') === $val ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
            </div>
        </div>

        
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="font-bold text-lg mb-4 border-b border-gray-200 pb-2">تنظیمات Enterprise</h2>
            <p class="text-gray-500 text-sm mb-4">تنظیمات ایمیل، پیامک، کلیدهای API و Feature flags برای قابلیت‌های سطح enterprise.</p>

            <div class="space-y-6">
                <h3 class="font-semibold text-base">ایمیل</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">درایور</label>
                        <select name="email_driver" class="form-control">
                            <?php $__currentLoopData = ['smtp' => 'SMTP', 'sendmail' => 'Sendmail', 'mailgun' => 'Mailgun', 'ses' => 'AWS SES']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($val); ?>" <?php echo e(old('email_driver', $email['email_driver'] ?? 'smtp') === $val ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">هاست</label>
                        <input type="text" name="email_host" class="form-control" value="<?php echo e(old('email_host', $email['email_host'] ?? '')); ?>" placeholder="smtp.example.com">
                    </div>
                    <div>
                        <label class="form-label">پورت</label>
                        <input type="text" name="email_port" class="form-control w-24" value="<?php echo e(old('email_port', $email['email_port'] ?? '587')); ?>" placeholder="587">
                    </div>
                    <div>
                        <label class="form-label">نام کاربری</label>
                        <input type="text" name="email_username" class="form-control" value="<?php echo e(old('email_username', $email['email_username'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="form-label">رمز عبور</label>
                        <input type="password" name="email_password" class="form-control" value="<?php echo e(old('email_password', $email['email_password'] ?? '')); ?>" placeholder="••••••••" autocomplete="new-password">
                        <p class="text-gray-500 text-xs mt-1">خالی برای نگه‌داشتن مقدار فعلی</p>
                    </div>
                    <div>
                        <label class="form-label">رمزنگاری</label>
                        <select name="email_encryption" class="form-control">
                            <?php $__currentLoopData = ['tls' => 'TLS', 'ssl' => 'SSL', '' => 'بدون']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($val); ?>" <?php echo e(old('email_encryption', $email['email_encryption'] ?? 'tls') === $val ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">آدرس فرستنده</label>
                        <input type="email" name="email_from_address" class="form-control" value="<?php echo e(old('email_from_address', $email['email_from_address'] ?? '')); ?>" placeholder="noreply@example.com">
                    </div>
                    <div>
                        <label class="form-label">نام فرستنده</label>
                        <input type="text" name="email_from_name" class="form-control" value="<?php echo e(old('email_from_name', $email['email_from_name'] ?? '')); ?>" placeholder="پلتفرم SaaS">
                    </div>
                </div>

                <h3 class="font-semibold text-base pt-4 border-t border-gray-100">پیامک</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">درایور</label>
                        <select name="sms_driver" class="form-control">
                            <?php $__currentLoopData = ['melipayamak' => 'ملی‌پیامک', 'kavenegar' => 'کاوه‌نگار', 'ghasedak' => 'قاصدک']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($val); ?>" <?php echo e(old('sms_driver', $sms['sms_driver'] ?? 'melipayamak') === $val ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">کلید API</label>
                        <input type="password" name="sms_api_key" class="form-control" value="<?php echo e(old('sms_api_key', $sms['sms_api_key'] ?? '')); ?>" placeholder="••••••••" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="form-label">شماره خط</label>
                        <input type="text" name="sms_line_number" class="form-control" value="<?php echo e(old('sms_line_number', $sms['sms_line_number'] ?? '')); ?>" placeholder="پیش‌شماره و شماره">
                    </div>
                </div>

                <h3 class="font-semibold text-base pt-4 border-t border-gray-100">کلیدهای API</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">کلید Google Maps</label>
                        <input type="text" name="api_google_maps_key" class="form-control" value="<?php echo e(old('api_google_maps_key', $apiKeys['api_google_maps_key'] ?? '')); ?>" placeholder="AIza...">
                    </div>
                    <div>
                        <label class="form-label">کلید سایت reCAPTCHA</label>
                        <input type="text" name="api_recaptcha_site_key" class="form-control" value="<?php echo e(old('api_recaptcha_site_key', $apiKeys['api_recaptcha_site_key'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="form-label">کلید مخفی reCAPTCHA</label>
                        <input type="password" name="api_recaptcha_secret_key" class="form-control" value="<?php echo e(old('api_recaptcha_secret_key', $apiKeys['api_recaptcha_secret_key'] ?? '')); ?>" placeholder="••••••••">
                    </div>
                </div>

                <h3 class="font-semibold text-base pt-4 border-t border-gray-100">Feature Flags</h3>
                <p class="text-gray-500 text-sm">فعال‌سازی قابلیت‌های سطح enterprise برای کل پلتفرم.</p>
                <div class="space-y-3">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="feature_marketplace_sync" value="1" <?php echo e(old('feature_marketplace_sync', $featureFlags['feature_marketplace_sync'] ?? '0') === '1' ? 'checked' : ''); ?> class="rounded border-gray-300">
                        <span>همگام‌سازی مارکت‌پلیس (ISAM, Torob, eMalls)</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="feature_multi_currency" value="1" <?php echo e(old('feature_multi_currency', $featureFlags['feature_multi_currency'] ?? '0') === '1' ? 'checked' : ''); ?> class="rounded border-gray-300">
                        <span>چندارزی پویا</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="feature_wallet" value="1" <?php echo e(old('feature_wallet', $featureFlags['feature_wallet'] ?? '0') === '1' ? 'checked' : ''); ?> class="rounded border-gray-300">
                        <span>کیف پول</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="feature_advanced_analytics" value="1" <?php echo e(old('feature_advanced_analytics', $featureFlags['feature_advanced_analytics'] ?? '0') === '1' ? 'checked' : ''); ?> class="rounded border-gray-300">
                        <span>تحلیل پیشرفته</span>
                    </label>
                </div>
            </div>
        </div>

        
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="font-bold text-lg mb-4 border-b border-gray-200 pb-2">حجم و آپلود فایل</h2>
            <p class="text-gray-500 text-sm mb-4">حداکثر حجم مجاز برای آپلود تصاویر (مثلاً در صفحه‌ساز و آیکن صفحات) را بر اساس کیلوبایت تنظیم کنید. مقدار پیش‌فرض ۵۱۲۰ یعنی ۵ مگابایت.</p>
            <div class="space-y-4">
                <div class="flex items-center gap-3 flex-wrap">
                    <label class="form-label mb-0">حداکثر حجم هر تصویر (کیلوبایت)</label>
                    <input type="number" name="upload_image_max_kb" class="form-control w-32" value="<?php echo e(old('upload_image_max_kb', $upload['upload_image_max_kb'] ?? '5120')); ?>" min="100" max="20480" placeholder="5120">
                    <span class="text-gray-500 text-sm">(مثال: 5120 = ۵ مگابایت، 10240 = ۱۰ مگ)</span>
                </div>
            </div>
        </div>

        
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="font-bold text-lg mb-4 border-b border-gray-200 pb-2">فونت‌ها و اندازه</h2>
            <p class="text-gray-500 text-sm mb-4">فونت متن، فونت عناوین و اندازه را تنظیم کنید. می‌توانید تا ۵ فونت اضافی با لینک (مثلاً از Google Fonts) اضافه کنید و نام فونت را در فیلدهای «فونت متن» یا «فونت عناوین» بنویسید.</p>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">فونت متن (بدن)</label>
                        <input type="text" name="font_body" class="form-control" value="<?php echo e(old('font_body', $font['font_body'] ?? '')); ?>" placeholder="Vazirmatn, Tahoma, sans-serif">
                    </div>
                    <div>
                        <label class="form-label">فونت عناوین</label>
                        <input type="text" name="font_title" class="form-control" value="<?php echo e(old('font_title', $font['font_title'] ?? '')); ?>" placeholder="Vazirmatn, Tahoma, sans-serif">
                    </div>
                    <div>
                        <label class="form-label">اندازه متن پایه (px)</label>
                        <input type="text" name="font_size_base" class="form-control w-24" value="<?php echo e(old('font_size_base', $font['font_size_base'] ?? '16')); ?>" placeholder="16">
                    </div>
                    <div>
                        <label class="form-label">ضریب اندازه عناوین (مثلاً 1.25)</label>
                        <input type="text" name="font_size_title" class="form-control w-24" value="<?php echo e(old('font_size_title', $font['font_size_title'] ?? '1.25')); ?>" placeholder="1.25">
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-200">
                    <h3 class="font-bold mb-3">فونت‌های اضافی (۳ تا ۵ فونت)</h3>
                    <p class="text-gray-500 text-xs mb-3">لینک CSS فونت (مثلاً از Google Fonts) و نام فونت را وارد کنید. سپس نام فونت را در فیلدهای بالا استفاده کنید.</p>
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="form-label text-xs">فونت <?php echo e($i); ?> – نام</label>
                                <input type="text" name="font_custom_<?php echo e($i); ?>_name" class="form-control" value="<?php echo e(old('font_custom_'.$i.'_name', $font['font_custom_'.$i.'_name'] ?? '')); ?>" placeholder="مثال: Vazirmatn">
                            </div>
                            <div>
                                <label class="form-label text-xs">فونت <?php echo e($i); ?> – لینک CSS</label>
                                <input type="url" name="font_custom_<?php echo e($i); ?>_url" class="form-control" value="<?php echo e(old('font_custom_'.$i.'_url', $font['font_custom_'.$i.'_url'] ?? '')); ?>" placeholder="https://fonts.googleapis.com/css2?family=...">
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">ذخیره همه تنظیمات</button>
        </div>
    </form>

</div>

<?php $__env->startPush('scripts'); ?>
<script>
document.querySelectorAll('input[type="color"]').forEach(function(colorInput) {
    var key = colorInput.id.replace('color_', '');
    var textInput = document.querySelector('input[name="' + key + '"]');
    if (textInput) {
        colorInput.addEventListener('input', function() { textInput.value = this.value; });
        textInput.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) colorInput.value = this.value;
        });
    }
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/platform-settings/index.blade.php ENDPATH**/ ?>