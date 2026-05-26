<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'name' => 'persian_date',
    'value' => '',
    'placeholder' => '۱۴۰۳/۰۷/۱۵',
    'id' => null,
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'name' => 'persian_date',
    'value' => '',
    'placeholder' => '۱۴۰۳/۰۷/۱۵',
    'id' => null,
]); ?>
<?php foreach (array_filter(([
    'name' => 'persian_date',
    'value' => '',
    'placeholder' => '۱۴۰۳/۰۷/۱۵',
    'id' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>
<?php
    $id = $id ?? 'persian-date-' . preg_replace('/[^a-z0-9]/', '-', $name);
?>
<input type="text"
       name="<?php echo e($name); ?>"
       id="<?php echo e($id); ?>"
       value="<?php echo e($value); ?>"
       placeholder="<?php echo e($placeholder); ?>"
       autocomplete="off"
       dir="ltr"
       class="form-control persian-date-input"
       <?php echo e($attributes->except(['class'])->merge([])); ?>>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/components/persian-date-input.blade.php ENDPATH**/ ?>