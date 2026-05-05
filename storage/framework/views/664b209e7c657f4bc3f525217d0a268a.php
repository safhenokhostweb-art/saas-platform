<?php
    $designSystem = app(\App\Services\DesignSystemService::class);
    $cssVars = $designSystem->getCssVariables(null);
    $fontImports = $designSystem->getFontImports(null);
?>
<?php $__currentLoopData = $fontImports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <link rel="stylesheet" href="<?php echo e($url); ?>" crossorigin="anonymous">
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<style id="platform-theme">
:root {
<?php $__currentLoopData = $cssVars; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $var => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php echo e($var); ?>: <?php echo e($value); ?>;
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
}
body { font-family: var(--ds-font-body); font-size: var(--ds-font-size-base); }
h1, h2, h3, h4, h5, h6, .font-title { font-family: var(--ds-font-title); font-size: calc(1em * var(--ds-font-size-title)); color: var(--ds-title-color); }
.navbar { background: var(--ds-menu-bg) !important; }
.navbar-brand { color: var(--ds-primary) !important; }
.navbar-link { color: var(--ds-menu-text) !important; }
.navbar-link:hover, .navbar-link.text-blue-600 { color: var(--ds-menu-link-active) !important; }
.btn-primary { background: var(--ds-button-primary-bg) !important; color: var(--ds-button-primary-text) !important; }
a { color: var(--ds-link-color); }
a:hover { color: var(--ds-link-hover); }
.sidebar-link { color: var(--ds-sidebar-text); }
.sidebar-link:hover, .sidebar-link.active { background: var(--ds-sidebar-active-bg); color: var(--ds-sidebar-active-text); }
</style>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/partials/platform-styles.blade.php ENDPATH**/ ?>