

<?php $__env->startSection('title', 'فروشگاه'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto">
    <h2 class="text-xl font-bold mb-4">خرید لایسنس و ماژول</h2>
    <?php if($preselectedSiteId ?? null): ?>
        <?php $preselectedSite = auth()->user()->customer?->sites->firstWhere('id', $preselectedSiteId); ?>
        <?php if($preselectedSite): ?>
            <div class="alert alert-success mb-4">شما برای سایت «<?php echo e($preselectedSite->title); ?>» پشتیبانی یا ماژول خریداری می‌کنید. سایت از قبل انتخاب شده است.</div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if(session('success')): ?>
        <div class="alert alert-success"><?php echo e(session('success')); ?></div>
    <?php endif; ?>
    <?php if(session('error')): ?>
        <div class="alert alert-danger"><?php echo e(session('error')); ?></div>
    <?php endif; ?>

    <div class="grid gap-4">
        <?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="card p-4 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <strong><?php echo e($product->name_fa); ?></strong>
                    <span class="text-gray-500 text-sm block"><?php echo e($product->typeLabel()); ?></span>
                    <?php if($product->type === \App\Models\Product::TYPE_MODULE_ADDON && $product->module): ?>
                        <span class="text-gray-600 text-sm block mt-1"><?php echo e($product->module->title); ?></span>
                        <?php if($product->module->description): ?>
                            <p class="text-gray-500 text-xs mt-1"><?php echo e(Str::limit($product->module->description, 120)); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if($product->type === \App\Models\Product::TYPE_SUPPORT_PLAN && $product->supportPlan): ?>
                        <span class="text-gray-600 text-sm block mt-1"><?php echo e($product->supportPlan->title ?? 'پشتیبانی'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <?php
                        $price = $product->price_irr;
                        if ($wholesaleDiscount > 0) {
                            $price = (int) round($price * (100 - $wholesaleDiscount) / 100);
                        }
                    ?>
                    <span class="font-bold"><?php echo e(number_format($price)); ?> ریال</span>
                    <?php if($wholesaleDiscount > 0): ?>
                        <span class="text-gray-500 text-sm line-through"><?php echo e(number_format($product->price_irr)); ?></span>
                        <span class="text-green-600 text-xs">تخفیف عمده <?php echo e($wholesaleDiscount); ?>%</span>
                    <?php endif; ?>
                    <?php
                        $licensedModulesBySite = $licensedModulesBySite ?? [];
                    ?>
                    <form action="<?php echo e(route('store.cart.add')); ?>" method="post" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="product_id" value="<?php echo e($product->id); ?>">
                        <?php if(in_array($product->type, [\App\Models\Product::TYPE_MODULE_ADDON, \App\Models\Product::TYPE_SUPPORT_PLAN])): ?>
                            <select name="site_id" class="form-control inline-block w-auto" required>
                                <option value="">انتخاب سایت</option>
                                <?php $__currentLoopData = auth()->user()->customer?->sites ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php $siteHasModule = $product->module_id && in_array($product->module_id, $licensedModulesBySite[$site->id] ?? [], true); ?>
                                    <option value="<?php echo e($site->id); ?>" <?php echo e(old('site_id', $preselectedSiteId ?? null) == $site->id ? 'selected' : ''); ?>>
                                        <?php echo e($site->title); ?> (<?php echo e($site->domain ?: '—'); ?>)<?php echo e($siteHasModule ? ' — خریداری‌شده' : ''); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <?php if($product->module_id && collect($licensedModulesBySite)->flatten()->unique()->contains($product->module_id)): ?>
                                <span class="inline-flex px-2 py-0.5 rounded text-xs bg-green-100 text-green-800 mr-1">برای حداقل یک سایت دارید</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">افزودن به سبد</button>
                    </form>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p class="text-gray-500">محصولی برای فروش تعریف نشده است.</p>
        <?php endif; ?>
    </div>

    <p class="mt-4"><a href="<?php echo e(route('store.cart')); ?>" class="btn btn-primary">مشاهده سبد خرید</a></p>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/store/index.blade.php ENDPATH**/ ?>