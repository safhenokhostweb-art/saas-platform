{{-- Floating Quick-Search Widget — draggable teal-glow square icon --}}
@php
    $fswItems = [];
    if (auth()->check()) {
        if (auth()->user()->is_admin) {
            foreach (\App\Helpers\AdminMenu::getGroups() as $groupLabel => $keys) {
                foreach ($keys as $key) {
                    if (\App\Helpers\AdminMenu::isMenuItemVisible($key)) {
                        try { $fswUrl = route(\App\Helpers\AdminMenu::getRoute($key)); } catch (\Throwable $e) { $fswUrl = '#'; }
                        $fswItems[] = [
                            'group' => $groupLabel,
                            'key'   => $key,
                            'title' => \App\Helpers\ModuleLabels::getTitle($key, null),
                            'url'   => $fswUrl,
                        ];
                    }
                }
            }
        } else {
            foreach (\App\Helpers\CustomerMenu::getGroups() as $groupLabel => $keys) {
                foreach ($keys as $key) {
                    if (\App\Helpers\CustomerMenu::isVisible($key)) {
                        try { $fswUrl = route(\App\Helpers\CustomerMenu::getRoute($key)); } catch (\Throwable $e) { $fswUrl = '#'; }
                        $fswItems[] = [
                            'group' => $groupLabel,
                            'key'   => $key,
                            'title' => \App\Helpers\ModuleLabels::getTitle($key),
                            'url'   => $fswUrl,
                        ];
                    }
                }
            }
        }
    }
@endphp

<style>
/* ══════════════════════════════════════════════
   Floating Search Widget — teal glow square icon
   ══════════════════════════════════════════════ */
#fsw-btn {
    position: fixed;
    bottom: 1.5rem;
    left: 1.5rem;
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: linear-gradient(135deg, #0e7490 0%, #0891b2 55%, #06b6d4 100%);
    border: 1.5px solid rgba(6, 182, 212, 0.45);
    cursor: grab;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9996;
    user-select: none;
    touch-action: none;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    animation: fsw-glow-pulse 2.4s ease-in-out infinite;
    outline: none;
}
#fsw-btn:hover { transform: scale(1.07); }
#fsw-btn:active { cursor: grabbing; transform: scale(0.94); animation-play-state: paused; }
#fsw-btn.fsw-dragging { cursor: grabbing; animation-play-state: paused; box-shadow: 0 8px 40px rgba(6,182,212,0.65); }
#fsw-btn svg { pointer-events: none; }

@keyframes fsw-glow-pulse {
    0%, 100% {
        box-shadow:
            0 4px 18px rgba(6, 182, 212, 0.50),
            0 0 0 0 rgba(6, 182, 212, 0.55),
            inset 0 1px 0 rgba(255,255,255,0.25);
    }
    40% {
        box-shadow:
            0 6px 28px rgba(6, 182, 212, 0.72),
            0 0 0 9px rgba(6, 182, 212, 0.10),
            inset 0 1px 0 rgba(255,255,255,0.25);
    }
    70% {
        box-shadow:
            0 5px 22px rgba(6, 182, 212, 0.58),
            0 0 0 14px rgba(6, 182, 212, 0.0),
            inset 0 1px 0 rgba(255,255,255,0.25);
    }
}

/* ── Backdrop ── */
#fsw-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: flex-start;
    justify-content: center;
    padding-top: 9vh;
    background: rgba(10, 22, 40, 0.52);
    backdrop-filter: blur(7px);
    -webkit-backdrop-filter: blur(7px);
}
#fsw-modal.fsw-open { display: flex; }

/* ── Dialog box ── */
#fsw-dialog {
    width: min(640px, 92vw);
    background: #ffffff;
    border-radius: 20px;
    box-shadow:
        0 24px 64px rgba(10, 22, 40, 0.28),
        0 0 0 1px rgba(6, 182, 212, 0.18),
        0 0 0 4px rgba(6, 182, 212, 0.06);
    overflow: hidden;
    animation: fsw-dialog-in 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}
@keyframes fsw-dialog-in {
    from { opacity: 0; transform: translateY(-18px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ── Search bar ── */
#fsw-search-bar {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(to left, #f0fdfe 0%, #ffffff 60%);
    direction: rtl;
}
#fsw-search-bar .fsw-search-icon { color: #0891b2; flex-shrink: 0; }
#fsw-input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 1rem;
    font-family: Vazirmatn, Tahoma, sans-serif;
    background: transparent;
    color: #0f172a;
    direction: rtl;
    text-align: right;
}
#fsw-input::placeholder { color: #94a3b8; }
#fsw-close-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px 5px;
    color: #94a3b8;
    border-radius: 7px;
    line-height: 1;
    flex-shrink: 0;
    transition: color 0.12s, background 0.12s;
}
#fsw-close-btn:hover { color: #475569; background: #f1f5f9; }
#fsw-kbd-hint {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 7px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.68rem;
    color: #64748b;
    font-family: monospace;
    flex-shrink: 0;
    white-space: nowrap;
}

/* ── Results ── */
#fsw-results-wrap {
    max-height: min(420px, 58vh);
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(6,182,212,0.25) transparent;
    direction: rtl;
}
#fsw-results-wrap::-webkit-scrollbar { width: 4px; }
#fsw-results-wrap::-webkit-scrollbar-thumb { background: rgba(6,182,212,0.22); border-radius: 4px; }
#fsw-results-wrap::-webkit-scrollbar-thumb:hover { background: rgba(6,182,212,0.4); }

.fsw-group-header {
    padding: 0.45rem 1.2rem 0.2rem;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: #94a3b8;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    direction: rtl;
    text-align: right;
}
.fsw-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 1.2rem;
    cursor: pointer;
    direction: rtl;
    text-align: right;
    text-decoration: none !important;
    color: #1e293b;
    transition: background 0.1s;
    border-radius: 0;
}
.fsw-item:hover, .fsw-item.is-active {
    background: linear-gradient(to left, #ecfeff 0%, #f0fdfe 100%);
    color: #0e7490;
}
.fsw-item-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: linear-gradient(135deg, #cffafe 0%, #a5f3fc 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #0891b2;
    transition: background 0.12s;
}
.fsw-item:hover .fsw-item-icon,
.fsw-item.is-active .fsw-item-icon {
    background: linear-gradient(135deg, #a5f3fc 0%, #67e8f9 100%);
}
.fsw-item-body { flex: 1; min-width: 0; }
.fsw-item-title { font-size: 0.875rem; font-weight: 600; line-height: 1.3; }
.fsw-item-group { font-size: 0.72rem; color: #94a3b8; margin-top: 1px; }
.fsw-item-arrow {
    flex-shrink: 0;
    color: #cbd5e1;
    transition: color 0.12s, transform 0.12s;
}
.fsw-item:hover .fsw-item-arrow,
.fsw-item.is-active .fsw-item-arrow { color: #0891b2; transform: translateX(-3px); }

/* ── Empty state ── */
#fsw-empty {
    padding: 3rem 1.5rem;
    text-align: center;
    direction: rtl;
    display: none;
}
#fsw-empty svg { margin: 0 auto 0.75rem; opacity: 0.35; }
#fsw-empty p { color: #94a3b8; font-size: 0.9rem; }

/* ── Footer hint ── */
#fsw-footer {
    padding: 0.6rem 1.2rem;
    border-top: 1px solid #f1f5f9;
    display: flex;
    gap: 1rem;
    font-size: 0.7rem;
    color: #94a3b8;
    direction: rtl;
    background: #fafafa;
}
#fsw-footer kbd {
    display: inline-flex;
    align-items: center;
    padding: 1px 5px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.68rem;
    color: #475569;
}
</style>

{{-- Draggable icon button --}}
<button id="fsw-btn" type="button" aria-label="جستجوی سریع در پنل (Ctrl+K)" title="جستجوی سریع (Ctrl+K)">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="#fff" stroke-width="2.3"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="11" cy="11" r="8"/>
        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
</button>

{{-- Search modal --}}
<div id="fsw-modal" role="dialog" aria-modal="true" aria-label="جستجوی سریع">
    <div id="fsw-dialog">

        {{-- Search bar --}}
        <div id="fsw-search-bar">
            <svg class="fsw-search-icon" xmlns="http://www.w3.org/2000/svg" width="19" height="19"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                 stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="search" id="fsw-input"
                   placeholder="جستجو در امکانات، ماژول‌ها و بخش‌های پنل…"
                   autocomplete="off" spellcheck="false" aria-label="جستجو">
            <span class="fsw-kbd-hint">Ctrl K</span>
            <button id="fsw-close-btn" type="button" aria-label="بستن">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none"
                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                     stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        {{-- Results --}}
        <div id="fsw-results-wrap">
            <div id="fsw-results"></div>
            <div id="fsw-empty">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none"
                     stroke="#94a3b8" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    <line x1="8" y1="11" x2="14" y2="11" stroke-linecap="round"/>
                </svg>
                <p>نتیجه‌ای یافت نشد</p>
            </div>
        </div>

        {{-- Footer --}}
        <div id="fsw-footer">
            <span><kbd>↑</kbd> <kbd>↓</kbd> ناوبری</span>
            <span><kbd>↵</kbd> رفتن</span>
            <span><kbd>Esc</kbd> بستن</span>
        </div>

    </div>
</div>

<script>
(function () {
    'use strict';

    /* ── Data ── */
    var ITEMS = @json($fswItems);

    /* ── Elements ── */
    var btn        = document.getElementById('fsw-btn');
    var modal      = document.getElementById('fsw-modal');
    var dialog     = document.getElementById('fsw-dialog');
    var input      = document.getElementById('fsw-input');
    var results    = document.getElementById('fsw-results');
    var emptyEl    = document.getElementById('fsw-empty');
    var closeBtn   = document.getElementById('fsw-close-btn');

    var activeIdx  = -1;
    var rendered   = [];

    /* ── Helpers ── */
    function norm(s) {
        return (s || '').replace(/[‌‍\s]+/g, '').toLowerCase();
    }
    function esc(s) {
        return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    /* ── Open / Close ── */
    function openModal() {
        modal.classList.add('fsw-open');
        input.value = '';
        render('');
        requestAnimationFrame(function(){ input.focus(); });
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        modal.classList.remove('fsw-open');
        activeIdx = -1;
        document.body.style.overflow = '';
    }

    /* ── Render results ── */
    function render(q) {
        var nq = norm(q);
        var filtered = nq === ''
            ? ITEMS
            : ITEMS.filter(function(it) {
                return norm(it.title).indexOf(nq) !== -1
                    || norm(it.group).indexOf(nq) !== -1
                    || norm(it.key).indexOf(nq) !== -1;
            });

        rendered = filtered;
        activeIdx = filtered.length > 0 ? 0 : -1;
        results.innerHTML = '';

        if (filtered.length === 0) {
            emptyEl.style.display = 'block';
            return;
        }
        emptyEl.style.display = 'none';

        var seenGroups = {};
        filtered.forEach(function(item, i) {
            if (!seenGroups[item.group]) {
                seenGroups[item.group] = true;
                var gh = document.createElement('div');
                gh.className = 'fsw-group-header';
                gh.textContent = item.group;
                results.appendChild(gh);
            }
            var a = document.createElement('a');
            a.href = item.url;
            a.className = 'fsw-item' + (i === 0 ? ' is-active' : '');
            a.setAttribute('data-i', i);
            a.innerHTML =
                '<div class="fsw-item-icon">' +
                  '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>' +
                '</div>' +
                '<div class="fsw-item-body">' +
                  '<div class="fsw-item-title">' + esc(item.title) + '</div>' +
                  '<div class="fsw-item-group">' + esc(item.group) + '</div>' +
                '</div>' +
                '<svg class="fsw-item-arrow" xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>';
            results.appendChild(a);
        });
    }

    function setActive(idx) {
        var items = results.querySelectorAll('.fsw-item');
        items.forEach(function(el, i) {
            el.classList.toggle('is-active', i === idx);
            if (i === idx) el.scrollIntoView({ block: 'nearest' });
        });
        activeIdx = idx;
    }

    /* ── Events ── */
    input.addEventListener('input', function() { render(input.value); });

    input.addEventListener('keydown', function(e) {
        var items = results.querySelectorAll('.fsw-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(Math.min(activeIdx + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(Math.max(activeIdx - 1, 0));
        } else if (e.key === 'Enter') {
            if (items[activeIdx]) { items[activeIdx].click(); }
        }
    });

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('mousedown', function(e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            modal.classList.contains('fsw-open') ? closeModal() : openModal();
        }
        if (e.key === 'Escape' && modal.classList.contains('fsw-open')) closeModal();
    });

    /* ── Drag & Drop ── */
    var wasDragged = false, isDragging = false;
    var dragStartX, dragStartY, btnX0, btnY0;

    function startDrag(cx, cy) {
        wasDragged = false;
        isDragging = false;
        dragStartX = cx;
        dragStartY = cy;
        var r = btn.getBoundingClientRect();
        btnX0 = r.left;
        btnY0 = r.top;
    }

    function moveDrag(cx, cy) {
        var dx = cx - dragStartX, dy = cy - dragStartY;
        if (!isDragging && (Math.abs(dx) > 5 || Math.abs(dy) > 5)) {
            isDragging = true;
            wasDragged = true;
            btn.classList.add('fsw-dragging');
        }
        if (!isDragging) return;
        wasDragged = true;
        var W = window.innerWidth, H = window.innerHeight;
        var w = btn.offsetWidth, h = btn.offsetHeight;
        var nx = Math.max(8, Math.min(btnX0 + dx, W - w - 8));
        var ny = Math.max(8, Math.min(btnY0 + dy, H - h - 8));
        btn.style.left   = nx + 'px';
        btn.style.top    = ny + 'px';
        btn.style.bottom = 'auto';
        btn.style.right  = 'auto';
        savePos(nx, ny);
    }

    function endDrag() {
        isDragging = false;
        btn.classList.remove('fsw-dragging');
        setTimeout(function() { wasDragged = false; }, 80);
    }

    btn.addEventListener('mousedown', function(e) {
        if (e.button !== 0) return;
        startDrag(e.clientX, e.clientY);
        e.preventDefault();
    });
    document.addEventListener('mousemove', function(e) {
        if (dragStartX === undefined) return;
        moveDrag(e.clientX, e.clientY);
    });
    document.addEventListener('mouseup', function() {
        if (dragStartX === undefined) return;
        endDrag();
        dragStartX = undefined;
    });

    btn.addEventListener('touchstart', function(e) {
        var t = e.touches[0];
        startDrag(t.clientX, t.clientY);
    }, { passive: true });
    document.addEventListener('touchmove', function(e) {
        if (dragStartX === undefined) return;
        moveDrag(e.touches[0].clientX, e.touches[0].clientY);
    }, { passive: true });
    document.addEventListener('touchend', function() {
        if (dragStartX === undefined) return;
        endDrag();
        dragStartX = undefined;
    });

    btn.addEventListener('click', function() {
        if (!wasDragged) openModal();
    });

    /* ── Persist position ── */
    function savePos(x, y) {
        try { localStorage.setItem('fsw-saas-pos', JSON.stringify({ x: x, y: y })); } catch (e) {}
    }
    (function restorePos() {
        try {
            var p = JSON.parse(localStorage.getItem('fsw-saas-pos'));
            if (p && typeof p.x === 'number' && typeof p.y === 'number') {
                var W = window.innerWidth, H = window.innerHeight;
                var w = btn.offsetWidth || 52, h = btn.offsetHeight || 52;
                btn.style.left   = Math.max(8, Math.min(p.x, W - w - 8)) + 'px';
                btn.style.top    = Math.max(8, Math.min(p.y, H - h - 8)) + 'px';
                btn.style.bottom = 'auto';
                btn.style.right  = 'auto';
            }
        } catch (e) {}
    })();

})();
</script>
