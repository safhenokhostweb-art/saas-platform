

<?php $__env->startSection('title', 'فروشگاه ماژول‌ها'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-6xl mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">فروشگاه ماژول‌ها</h1>
            <p class="text-gray-600 mt-1">ماژول مورد نظر را انتخاب و برای یکی از سایت‌های خود به سبد خرید اضافه کنید.</p>
        </div>
        <a href="<?php echo e(route('store.cart')); ?>" class="btn btn-primary inline-flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            سبد خرید
        </a>
    </div>

    <?php if($preselectedSiteId ?? null): ?>
        <?php $preselectedSite = auth()->user()->customer?->sites->firstWhere('id', $preselectedSiteId); ?>
        <?php if($preselectedSite): ?>
            <div class="alert alert-success mb-4">شما برای سایت «<?php echo e($preselectedSite->title); ?>» خرید می‌کنید. سایت از قبل انتخاب شده است.</div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if(session('success')): ?>
        <div class="alert alert-success"><?php echo e(session('success')); ?></div>
    <?php endif; ?>
    <?php if(session('error')): ?>
        <div class="alert alert-danger"><?php echo e(session('error')); ?></div>
    <?php endif; ?>

    <?php if($products->isEmpty()): ?>
        <div class="card p-8 text-center">
            <p class="text-gray-500">در حال حاضر ماژولی برای فروش در فروشگاه تعریف نشده است.</p>
            <p class="mt-2 text-sm text-gray-400">از پنل ادمین پلتفرم می‌توانید ماژول‌ها را به فروشگاه اضافه کنید.</p>
        </div>
    <?php else: ?>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $module = $product->module;
                    $price = $product->price_irr;
                    if ($wholesaleDiscount > 0) {
                        $price = (int) round($price * (100 - $wholesaleDiscount) / 100);
                    }
                    $sites = auth()->user()->customer?->sites ?? collect();
                    $hasForAnySite = $product->module_id && collect($enabledModuleIdsBySite ?? [])->flatten()->unique()->contains($product->module_id);
                ?>
                <div class="card overflow-hidden hover:shadow-lg transition-shadow duration-200 flex flex-col">
                    <div class="p-5 flex-1 flex flex-col">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <h3 class="font-bold text-lg text-gray-800"><?php echo e($product->name_fa); ?></h3>
                            <?php if($hasForAnySite): ?>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800 shrink-0">خریداری‌شده</span>
                            <?php endif; ?>
                        </div>
                        <?php if($module?->short_description): ?>
                            <p class="text-gray-600 text-sm mb-3 line-clamp-3"><?php echo e($module->short_description); ?></p>
                        <?php elseif($module?->description): ?>
                            <p class="text-gray-600 text-sm mb-3 line-clamp-3"><?php echo e(Str::limit($module->description, 140)); ?></p>
                        <?php else: ?>
                            <p class="text-gray-500 text-sm mb-3">ماژول افزودنی برای سایت شما.</p>
                        <?php endif; ?>
                        <div class="mt-auto">
                            <div class="flex items-center gap-2 flex-wrap mb-3">
                                <span class="font-bold text-indigo-600 text-lg"><?php echo e(number_format($price)); ?></span>
                                <span class="text-gray-500 text-sm">ریال</span>
                                <?php if($wholesaleDiscount > 0): ?>
                                    <span class="text-gray-400 text-sm line-through"><?php echo e(number_format($product->price_irr)); ?></span>
                                    <span class="text-green-600 text-xs">تخفیف <?php echo e($wholesaleDiscount); ?>٪</span>
                                <?php endif; ?>
                            </div>
                            <form action="<?php echo e(route('store.cart.add')); ?>" method="post" class="space-y-2">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="product_id" value="<?php echo e($product->id); ?>">
                                <select name="site_id" class="form-control text-sm" required>
                                    <option value="">انتخاب سایت</option>
                                    <?php $__currentLoopData = $sites; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php $siteHasModule = $product->module_id && in_array($product->module_id, $enabledModuleIdsBySite[$site->id] ?? [], true); ?>
                                        <option value="<?php echo e($site->id); ?>" <?php echo e(old('site_id', $preselectedSiteId ?? null) == $site->id ? 'selected' : ''); ?> <?php echo e($siteHasModule ? 'disabled' : ''); ?>>
                                            <?php echo e($site->title); ?><?php echo e($siteHasModule ? ' — خریداری‌شده' : ''); ?>

                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                                <button type="submit" class="btn btn-primary w-full text-sm py-2">
                                    افزودن به سبد خرید
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <div class="mt-8 flex flex-wrap gap-4 justify-center">
            <a href="<?php echo e(route('store.cart')); ?>" class="btn btn-primary">مشاهده سبد خرید</a>
            <a href="<?php echo e(route('store.index')); ?>" class="btn border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">همه محصولات فروشگاه</a>
        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/store/modules.blade.php ENDPATH**/ ?>