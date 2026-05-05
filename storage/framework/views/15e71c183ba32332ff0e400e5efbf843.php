<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>پیش‌نمایش <?php echo e($zone === 'footer' ? 'فوتر' : 'هدر'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, sans-serif; direction: rtl; font-size: 16px; }
        .tb-section { padding: 1rem 0; }
        .tb-container { max-width: 80rem; margin: 0 auto; padding: 0 1rem; }
        .tb-hero { padding: 3rem 1rem; text-align: center; }
        .tb-hero-title { font-size: 2rem; margin: 0 0 0.5rem; }
        .tb-hero-subtitle { margin: 0 0 1rem; opacity: 0.9; }
        .tb-btn { display: inline-block; padding: 0.5rem 1.5rem; border-radius: 0.5rem; text-decoration: none; font-weight: 500; }
        .tb-btn-primary { background: var(--tb-primary, #4f46e5); color: #fff; }
        .tb-nav a { margin-left: 1rem; text-decoration: none; color: inherit; }
        .tb-heading { margin: 0 0 0.5rem; }
        .tb-image img { max-width: 100%; height: auto; }
        .tb-divider { border: none; border-top: 1px solid #e5e7eb; margin: 1rem 0; }
        .tb-social-icons a { margin-left: 0.75rem; }
    </style>
    <style><?php echo $css; ?></style>
</head>
<body>
    <?php if($zone === 'header'): ?>
    <header class="tb-preview-zone tb-header">
        <?php echo $html; ?>

    </header>
    <?php else: ?>
    <footer class="tb-preview-zone tb-footer">
        <?php echo $html; ?>

    </footer>
    <?php endif; ?>
</body>
</html>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/admin/theme-builder/preview-frame.blade.php ENDPATH**/ ?>