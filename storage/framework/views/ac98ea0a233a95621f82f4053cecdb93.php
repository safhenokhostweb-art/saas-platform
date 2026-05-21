
<?php
    try {
        $gw = \App\Helpers\PlatformSettings::allGuideWidget();
        $pos = \App\Helpers\PlatformSettings::guideWidgetPosition();
    } catch (\Throwable $e) {
        $gw = ['guide_widget_btn_bg' => '#2563eb', 'guide_widget_btn_text' => '#ffffff', 'guide_widget_panel_bg' => '#ffffff', 'guide_widget_panel_header_bg' => '#f9fafb', 'guide_widget_panel_text' => '#111827'];
        $pos = 'bottom-right';
    }
    $posStyle = match($pos) {
        'bottom-left' => 'bottom: 1.5rem; left: 1.5rem;',
        'top-right' => 'top: 1.5rem; right: 1.5rem;',
        'top-left' => 'top: 1.5rem; left: 1.5rem;',
        default => 'bottom: 1.5rem; right: 1.5rem;',
    };
?>
<div id="guide-widget" style="position: fixed; <?php echo e($posStyle); ?> font-family: inherit; z-index: 9997;">
    <button type="button" id="guide-widget-toggle" aria-label="راهنما" style="display: flex; align-items: center; justify-content: center; width: 3.5rem; height: 3.5rem; border-radius: 9999px; border: none; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); background: <?php echo e($gw['guide_widget_btn_bg'] ?? '#2563eb'); ?>; color: <?php echo e($gw['guide_widget_btn_text'] ?? '#ffffff'); ?>;">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </button>
    <div id="guide-widget-backdrop" style="display: none; position: fixed; inset: 0; z-index: 9998; background: rgba(0,0,0,0.4);" aria-hidden="true"></div>
    <div id="guide-widget-panel" style="display: none; position: fixed; top: 0; right: 0; height: 100%; width: 100%; max-width: 24rem; border-left: 1px solid #e5e7eb; box-shadow: -10px 0 25px -5px rgba(0,0,0,.1); flex-direction: column; z-index: 9999; background: <?php echo e($gw['guide_widget_panel_bg'] ?? '#ffffff'); ?>;">
        <div class="flex items-center justify-between p-4 border-b border-gray-200 shrink-0" style="background: <?php echo e($gw['guide_widget_panel_header_bg'] ?? '#f9fafb'); ?>; color: <?php echo e($gw['guide_widget_panel_text'] ?? '#111827'); ?>;">
            <h2 class="text-lg font-bold">راهنمای استفاده</h2>
            <button type="button" id="guide-widget-close" aria-label="بستن" class="p-2 rounded hover:opacity-80" style="color: inherit;">✕</button>
        </div>
        <div class="p-4 border-b border-gray-100 shrink-0">
            <input type="text" id="guide-widget-search" placeholder="جستجو در راهنما..." class="form-control w-full" dir="rtl" autocomplete="off">
        </div>
        <div id="guide-widget-results" class="flex-1 min-h-0 overflow-y-auto p-4" style="color: <?php echo e($gw['guide_widget_panel_text'] ?? '#111827'); ?>; background: <?php echo e($gw['guide_widget_panel_bg'] ?? '#ffffff'); ?>;">
            <p class="text-gray-500 text-sm">عبارتی تایپ کنید یا لیست زیر را ببینید.</p>
        </div>
    </div>
</div>
<script>
(function() {
    var searchUrl = <?php echo json_encode(route('admin.guide-search'), 15, 512) ?>;
    var toggle = document.getElementById('guide-widget-toggle');
    var panel = document.getElementById('guide-widget-panel');
    var closeBtn = document.getElementById('guide-widget-close');
    var backdrop = document.getElementById('guide-widget-backdrop');
    var searchInput = document.getElementById('guide-widget-search');
    var resultsEl = document.getElementById('guide-widget-results');
    var debounce = null;

    function openPanel() {
        if (panel) { panel.style.display = 'flex'; }
        if (backdrop) { backdrop.style.display = 'block'; }
        document.body.style.overflow = 'hidden';
        if (searchInput && searchInput.value.trim() === '') fetchResults('');
        if (searchInput) searchInput.focus();
    }
    function closePanel() {
        if (panel) { panel.style.display = 'none'; }
        if (backdrop) { backdrop.style.display = 'none'; }
        document.body.style.overflow = '';
    }

    function render(pages) {
        if (!pages || pages.length === 0) {
            resultsEl.innerHTML = '<p class="text-gray-500 text-sm">نتیجه‌ای یافت نشد.</p>';
            return;
        }
        resultsEl.innerHTML = '<ul class="space-y-2">' + pages.map(function(p) {
            return '<li><a href="' + p.url + '" class="block p-3 rounded-lg hover:bg-gray-100 text-right border border-gray-100" target="_blank" rel="noopener">' + (p.title || p.slug) + '</a></li>';
        }).join('') + '</ul>';
    }

    function fetchResults(q) {
        var url = searchUrl + (q ? '?q=' + encodeURIComponent(q) : '');
        resultsEl.innerHTML = '<p class="text-gray-500 text-sm">در حال جستجو...</p>';
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function(r) {
                if (!r.ok) throw new Error('خطا در پاسخ سرور');
                return r.json();
            })
            .then(function(data) { render(Array.isArray(data.pages) ? data.pages : []); })
            .catch(function() { resultsEl.innerHTML = '<p class="text-red-500 text-sm">خطا در بارگذاری. دوباره تلاش کنید.</p>'; });
    }

    if (toggle) toggle.addEventListener('click', openPanel);
    if (closeBtn) closeBtn.addEventListener('click', closePanel);
    if (backdrop) backdrop.addEventListener('click', closePanel);
    searchInput.addEventListener('input', function() {
        clearTimeout(debounce);
        var q = this.value.trim();
        debounce = setTimeout(function() { fetchResults(q); }, 300);
    });
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePanel();
    });
    if (searchInput && searchInput.value.trim() === '' && panel && panel.style.display !== 'none') fetchResults('');
})();
</script>
<?php /**PATH C:\laragon\www\saas-platform\resources\views/partials/guide-widget.blade.php ENDPATH**/ ?>