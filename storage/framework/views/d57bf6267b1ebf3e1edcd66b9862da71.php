<?php
$classes = match($priority ?? '') {
    'high'   => 'bg-red-100 text-red-800',
    'normal' => 'bg-blue-100 text-blue-700',
    'low'    => 'bg-gray-100 text-gray-500',
    default  => 'bg-gray-100 text-gray-500',
};
$label = match($priority ?? '') {
    'high'   => 'فوری',
    'normal' => 'معمولی',
    'low'    => 'کم',
    default  => $priority ?? '',
};
?>
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo e($classes); ?>">
    <?php echo e($label); ?>

</span>
<?php /**PATH C:\laragon\www\saas-platform\packages\saas-platform\modules\tickets\src\Providers/../../Resources/views/components/priority-badge.blade.php ENDPATH**/ ?>