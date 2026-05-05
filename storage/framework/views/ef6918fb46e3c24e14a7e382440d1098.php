

<?php $__env->startSection('title', $page->title . ' | ' . \App\Helpers\PlatformSettings::platformName()); ?>
<?php $__env->startSection('head'); ?>
    <?php
        $metaDesc = $page->meta_description ? e($page->meta_description) : e(\Illuminate\Support\Str::limit($page->title, 160));
    ?>
    <meta name="description" content="<?php echo e($metaDesc); ?>">
    <link rel="canonical" href="<?php echo e(url()->current()); ?>">
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo e(e($page->title)); ?>">
    <meta property="og:description" content="<?php echo e($metaDesc); ?>">
    <meta property="og:url" content="<?php echo e(url()->current()); ?>">
    <meta property="og:locale" content="fa_IR">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?php echo e(e($page->title)); ?>">
    <meta name="twitter:description" content="<?php echo e($metaDesc); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<article class="card p-6 md:p-8 max-w-4xl mx-auto">
    <header class="border-b border-gray-200 pb-4 mb-6">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900"><?php echo e($page->title); ?></h1>
    </header>

    <div class="page-content prose prose-lg max-w-none">
        <?php $content = $page->content; $blocks = isset($content['blocks']) && is_array($content['blocks']) ? $content['blocks'] : []; ?>
        <?php $__currentLoopData = $blocks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $block): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php echo $__env->make('page.partials.block', ['block' => $block], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if(empty($blocks)): ?>
            <p class="text-gray-500">محتوایی برای این صفحه تعریف نشده است.</p>
        <?php endif; ?>
    </div>
</article>
<?php $__env->startPush('scripts'); ?>
<script>
document.querySelectorAll('.gallery-carousel-wrap').forEach(function(wrap) {
    var track = wrap.querySelector('.gallery-track');
    var prev = wrap.querySelector('.gallery-prev');
    var next = wrap.querySelector('.gallery-next');
    if (!track || !prev || !next) return;
    prev.addEventListener('click', function() { track.scrollBy({ left: -340, behavior: 'smooth' }); });
    next.addEventListener('click', function() { track.scrollBy({ left: 340, behavior: 'smooth' }); });
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\saas-platform\resources\views/page/show.blade.php ENDPATH**/ ?>