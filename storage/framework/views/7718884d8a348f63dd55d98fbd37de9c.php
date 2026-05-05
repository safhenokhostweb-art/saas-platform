<?php
    $slot = $position ?? 'top';
    try {
        $globalPosition = \App\Models\Setting::get('page_menu_position', 'top');
        if ($globalPosition !== $slot) {
            $pages = collect();
        } else {
            $pages = \App\Models\Page::where('is_active', true)->orderBy('menu_order')->orderBy('title')->get();
        }
    } catch (\Throwable $e) {
        $pages = collect();
    }
?>
<?php if($pages->isNotEmpty()): ?>
    <nav class="pages-menu pages-menu-<?php echo e($slot); ?> flex <?php echo e(in_array($slot, ['left','right'], true) ? 'flex-col gap-2' : 'flex-wrap items-center gap-2 md:gap-4'); ?>" aria-label="صفحات ثابت">
        <?php $__currentLoopData = $pages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $isActive = request()->routeIs('page.show') && request()->route('slug') == $p->slug; ?>
            <a href="<?php echo e(route('page.show', $p->slug)); ?>" class="pages-menu-link inline-flex items-center gap-1.5 text-sm font-medium <?php echo e($isActive ? 'text-indigo-600' : 'text-gray-600 hover:text-indigo-600'); ?>">
                <?php if($p->icon_url): ?>
                    <img src="<?php echo e($p->icon_url); ?>" alt="" class="w-5 h-5 object-contain shrink-0" width="20" height="20">
                <?php else: ?>
                    <?php echo $__env->make('admin.pages.partials.icon-svg', ['name' => $p->icon_default ?? 'document', 'class' => 'w-5 h-5 shrink-0 text-current'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                <?php endif; ?>
                <span><?php echo e($p->title); ?></span>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </nav>
<?php endif; ?>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/partials/pages-menu.blade.php ENDPATH**/ ?>