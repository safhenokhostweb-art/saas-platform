<?php
    $featuresData = app(\App\Services\ModuleFeatureService::class)->getModuleFeatures($module ?? '');
?>

<?php if($featuresData): ?>
<div class="bg-white rounded-2xl shadow overflow-hidden mb-6">
    
    <div class="px-3 py-2 bg-gradient-to-l from-blue-50 to-indigo-50 border-b border-blue-100">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-xs font-bold text-gray-800 flex items-center gap-1">
                    <svg class="w-2 h-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    امکانات ماژول <?php echo e($featuresData['module_name']); ?>

                </h3>
                <p class="text-[10px] text-gray-500 mt-0.5">
                    نسخه <?php echo e($featuresData['version']); ?> | آخرین بروزرسانی: <?php echo e($featuresData['last_updated']); ?>

                </p>
            </div>
            <div class="flex items-center gap-1.5">
                <?php
                    $statusColors = [
                        'not_started' => 'bg-gray-100 text-gray-600',
                        'in_progress' => 'bg-amber-100 text-amber-700',
                        'completed' => 'bg-green-100 text-green-700',
                    ];
                    $statusLabels = [
                        'not_started' => 'شروع نشده',
                        'in_progress' => 'در حال توسعه',
                        'completed' => 'تکمیل شده',
                    ];
                    $completionStatus = $featuresData['completion_status'] ?? 'not_started';
                ?>
                <span class="text-[9px] px-1.5 py-0.5 rounded-full font-medium <?php echo e($statusColors[$completionStatus] ?? $statusColors['not_started']); ?>">
                    <?php echo e($statusLabels[$completionStatus] ?? 'نامشخص'); ?>

                </span>
            </div>
        </div>
    </div>

    
    <div class="p-2">
        <?php if(!empty($featuresData['features'])): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-1.5">
                <?php $__currentLoopData = $featuresData['features']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $priorityColors = [
                            'high' => 'bg-red-50 text-red-600 border-red-200',
                            'medium' => 'bg-amber-50 text-amber-600 border-amber-200',
                            'low' => 'bg-green-50 text-green-600 border-green-200',
                        ];
                        $categoryIcons = [
                            'core' => '⚙️',
                            'security' => '🔒',
                            'payment' => '💳',
                            'marketing' => '📢',
                            'analytics' => '📊',
                            'design' => '🎨',
                            'performance' => '⚡',
                            'utility' => '🛠️',
                            'automation' => '🤖',
                            'legal' => '📋',
                            'knowledge' => '📚',
                            'ui' => '🖥️',
                        ];
                        $icon = $categoryIcons[$feature['category']] ?? '📦';
                        $priorityClass = $priorityColors[$feature['priority']] ?? $priorityColors['medium'];
                    ?>
                    <div class="border rounded-md p-2 hover:shadow-sm transition-shadow duration-150 <?php echo e($feature['status'] === 'active' ? 'bg-white' : 'bg-gray-50 opacity-75'); ?>">
                        <div class="flex items-start gap-1.5">
                            <span class="text-xs flex-shrink-0 mt-0.5"><?php echo e($icon); ?></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <h4 class="text-[11px] font-semibold text-gray-800"><?php echo e($feature['title']); ?></h4>
                                    <span class="text-[9px] px-1 py-0.5 rounded border <?php echo e($priorityClass); ?>">
                                        <?php echo e(['high' => 'مهم', 'medium' => 'متوسط', 'low' => 'عادی'][$feature['priority']] ?? 'متوسط'); ?>

                                    </span>
                                    <?php if($feature['status'] !== 'active'): ?>
                                        <span class="text-[9px] px-1 py-0.5 rounded bg-gray-100 text-gray-500">غیرفعال</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-[10px] text-gray-500 mt-0.5 leading-relaxed"><?php echo e($feature['description'] ?? ''); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-gray-400">
                <svg class="w-6 h-6 mx-auto mb-1.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <p class="text-[11px]">هیچ ویژگی‌ای برای این ماژول تعریف نشده است.</p>
            </div>
        <?php endif; ?>
    </div>

    
    <?php if(!empty($featuresData['upcoming_features'])): ?>
    <div class="mx-2 pb-2">
        <h4 class="text-[9px] font-semibold text-gray-500 uppercase tracking-wider mb-1 flex items-center gap-1">
            <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            در دست توسعه
        </h4>
        <div class="flex flex-wrap gap-1">
            <?php $__currentLoopData = $featuresData['upcoming_features']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $upcoming): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <span class="text-[9px] px-1.5 py-0.5 bg-purple-50 text-purple-700 rounded border border-purple-200">
                    <?php echo e($upcoming['title']); ?>

                </span>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
    <?php endif; ?>

    
    <?php if(!empty($featuresData['changelog'])): ?>
    <div class="border-t border-gray-100 px-3 py-1 bg-gray-50">
        <details class="group">
            <summary class="text-[9px] text-gray-500 cursor-pointer hover:text-gray-700 flex items-center gap-1 select-none">
                <svg class="w-2 h-2 group-open:rotate-90 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                تاریخچه تغییرات
            </summary>
            <div class="mt-0.5 space-y-0.5">
                <?php $__currentLoopData = $featuresData['changelog']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="text-[9px] text-gray-600 flex items-center gap-1">
                        <span class="text-gray-400"><?php echo e($log['date']); ?></span>
                        <span class="text-blue-500 font-medium">v<?php echo e($log['version']); ?></span>
                        <span><?php echo e($log['description']); ?></span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </details>
    </div>
    <?php endif; ?>

    
    <?php if(!empty($featuresData['last_checkpoint'])): ?>
    <div class="border-t border-gray-100 px-3 py-1 flex items-center justify-between text-[9px] text-gray-400">
        <span>
            جلسه: <span class="font-mono text-gray-500"><?php echo e($featuresData['session_id'] ?? '—'); ?></span>
            | گام: <span class="text-gray-500"><?php echo e($featuresData['last_checkpoint']['step'] ?? '—'); ?></span>
        </span>
        <span class="text-gray-400"><?php echo e($featuresData['last_checkpoint']['message'] ?? ''); ?></span>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow p-3 mb-6 text-center text-gray-400">
    <p class="text-[11px]">فایل امکانات برای این ماژول یافت نشد.</p>
</div>
<?php endif; ?>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/components/module-features.blade.php ENDPATH**/ ?>