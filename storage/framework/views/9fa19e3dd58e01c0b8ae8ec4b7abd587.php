<?php
    $isStaff  = ($reply->author_type ?? '') === 'staff';
    $bgClass  = $isStaff ? 'bg-green-50 border-green-100' : 'bg-blue-50 border-blue-100';
    $initial  = mb_substr($reply->user?->name ?? ($isStaff ? 'پشتیبانی' : 'مشتری'), 0, 1);
    $nameColor = $isStaff ? 'text-green-700' : 'text-blue-700';
    $avatarBg  = $isStaff ? 'bg-green-200 text-green-700' : 'bg-blue-200 text-blue-700';
?>
<div class="rounded-xl border p-4 <?php echo e($bgClass); ?>">
    <div class="flex items-center gap-2 mb-2">
        <div class="w-7 h-7 rounded-full <?php echo e($avatarBg); ?> flex items-center justify-center text-xs font-bold">
            <?php echo e($initial); ?>

        </div>
        <div class="flex items-center gap-2">
            <p class="text-sm font-medium <?php echo e($nameColor); ?>">
                <?php echo e($reply->user?->name ?? ($isStaff ? 'پشتیبانی' : 'مشتری')); ?>

            </p>
            <?php if($isStaff): ?>
            <span class="text-xs bg-green-200 text-green-800 px-1.5 py-0.5 rounded">پشتیبان</span>
            <?php endif; ?>
        </div>
        <p class="text-xs text-gray-400 mr-auto"><?php echo e($reply->created_at->diffForHumans()); ?></p>
    </div>
    <p class="text-gray-700 text-sm leading-relaxed whitespace-pre-wrap"><?php echo e($reply->message); ?></p>

    <?php if(!empty($reply->attachments) && is_array($reply->attachments)): ?>
<div class="mt-2 flex flex-wrap gap-2">
    <?php $__currentLoopData = $reply->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(isset($file['path']) && isset($file['name'])): ?>
        <a href="<?php echo e(asset('storage/' . $file['path'])); ?>" target="_blank"
           class="text-xs bg-white text-gray-600 border border-gray-200 px-2 py-1 rounded-full hover:bg-gray-50 transition">
            📎 <?php echo e($file['name']); ?>

        </a>
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\saas-platform\packages\saas-platform\modules\tickets\src\Providers/../../Resources/views/components/reply-item.blade.php ENDPATH**/ ?>