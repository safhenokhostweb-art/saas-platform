<!DOCTYPE html>
@php
    $seoService = app(\App\Services\Seo\SeoService::class);
    $metaData = $seoService->resolveForPreview();
    $metaArray = $metaData->toArray();
@endphp
{{-- Phase 3A: direction from the canonical projection (settings envelope →
    legacy global_styles → locale → host default), resolved in
    ThemeCanvasController::livePreview() — no longer hardcoded rtl. --}}
<html lang="{{ $metaArray['language'] }}" dir="{{ $metaArray['direction'] }}">
<head>
<meta charset="UTF-8">
<script>(function(){
    var KEY = 'tb-theme';
    function resolve(p) {
        if (p === 'system') {
            try { return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
            catch (e) { return 'light'; }
        }
        return p === 'dark' ? 'dark' : 'light';
    }
    function apply(p) {
        document.documentElement.setAttribute('data-theme', resolve(p));
        document.documentElement.setAttribute('data-theme-pref', p);
    }
    var pref = 'system';
    try { pref = localStorage.getItem(KEY) || 'system'; } catch (e) {}
    apply(pref);
    window.__tbTheme = {
        get: function () { return pref; },
        set: function (p) {
            pref = (p === 'light' || p === 'dark' || p === 'system') ? p : 'system';
            apply(pref);
            try { localStorage.setItem(KEY, pref); } catch (e) {}
            document.dispatchEvent(new CustomEvent('tb-theme-change', { detail: { pref: pref, resolved: resolve(pref) } }));
        },
        cycle: function () {
            var order = ['light', 'dark', 'system'];
            this.set(order[(order.indexOf(pref) + 1) % order.length]);
        }
    };
})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<x-seo-meta :meta-data="$metaData" />
{{-- Phase 0.4 (ADR-063): local Vazirmatn, exactly as customer-site's live
     page loads it — no Google Fonts. --}}
<link rel="preload" as="font" href="{{ asset('assets/fonts/vazirmatn/Vazirmatn-Regular.woff2') }}" type="font/woff2" crossorigin>
<link rel="preload" as="font" href="{{ asset('assets/fonts/vazirmatn/Vazirmatn-Bold.woff2') }}" type="font/woff2" crossorigin>
<style>@include('theme-runtime::css.fonts')</style>
<style>
*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
{{-- Canvas fidelity fix (2026-09-20): this iframe is what the Canvas
     editor's WYSIWYG surface actually shows — it used to carry its own
     independently hand-maintained, materially incomplete CSS subset (no
     theme-runtime::css.contract at all; zero rules for nav_header/
     nav_footer, hero_split/hero_gradient, gallery, promo_banner/countdown,
     wishlist/loyalty/installment) that had silently drifted from
     canvas-preview.blade.php's parity-audited copy. Root cause of the
     "navigation renders as raw unstyled links" / "hero renders as a
     placeholder" symptoms: the real block markup was always correct, it
     simply had no CSS to render with in this specific view. Now shares the
     exact same partial (which itself pulls in theme-runtime::css.contract)
     as canvas-preview.blade.php — a single, un-driftable source for both.
     Do not re-add any of these rules locally; edit the partial. --}}
@include('admin.theme-builder.partials.canvas-css-contract')

/* ── Editor canvas overlay styles ── */
.tb-canvas-block {
    position: relative;
    cursor: pointer;
    outline: 2px solid transparent;
    outline-offset: 0;
    transition: outline-color 0.12s ease;
    min-height: 16px;
}
.tb-canvas-block:hover {
    outline: 2px solid rgba(99, 102, 241, 0.55);
}
.tb-canvas-block.tb-selected {
    outline: 2px solid #4f46e5;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
}
.tb-block-badge {
    display: none;
    position: absolute;
    top: 0;
    right: 0;
    z-index: 200;
    background: #4f46e5;
    color: #fff;
    font-size: 10px;
    font-family: 'Vazirmatn', sans-serif;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 0 0 0 5px;
    pointer-events: none;
    white-space: nowrap;
    line-height: 1.6;
}
.tb-canvas-block:hover .tb-block-badge,
.tb-canvas-block.tb-selected .tb-block-badge {
    display: block;
}
.tb-drag-handle {
    display: none; position: absolute; inset-block-start: 4px; inset-inline-start: 4px;
    z-index: 240; width: 28px; height: 28px; border: 0; border-radius: 6px;
    background: #4338ca; color: #fff; cursor: grab; touch-action: none;
    align-items: center; justify-content: center; font-weight: 800; letter-spacing: -3px;
    box-shadow: 0 2px 8px rgba(15,23,42,.24);
}
.tb-canvas-block:hover > .tb-drag-handle,
.tb-canvas-block.tb-selected > .tb-drag-handle { display: flex; }
.tb-drag-handle:focus-visible { display: flex; outline: 3px solid #f59e0b; outline-offset: 2px; }
body.tb-pointer-dragging { cursor: grabbing; user-select: none; }
.tb-canvas-block.tb-drop-before { box-shadow: inset 0 4px 0 #4f46e5 !important; }
.tb-canvas-block.tb-drop-after { box-shadow: inset 0 -4px 0 #4f46e5 !important; }
.tb-canvas-block.tb-drop-inside { outline: 3px dashed #4f46e5 !important; outline-offset: -5px; }
.tb-canvas-block.tb-drop-invalid { outline: 3px dashed #dc2626 !important; cursor: not-allowed; }

/* ── Drop zones (TB3-03) ── */
.tb-drop-zone {
    height: 0;
    margin: 0;
    transition: height 0.15s ease, opacity 0.15s ease;
    position: relative;
    overflow: visible;
    opacity: 0;
}
body.tb-drag-active .tb-drop-zone {
    height: 10px;
    opacity: 1;
    background: rgba(99, 102, 241, 0.08);
    border-radius: 5px;
}
.tb-drop-zone.tb-drop-active {
    height: 10px !important;
    opacity: 1 !important;
    background: #4f46e5 !important;
    border-radius: 5px;
}
.tb-drop-zone.tb-drop-active::before {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 14px;
    height: 14px;
    background: #4f46e5;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #4f46e5;
}

/* ── Visibility toggles (TB3-05 advanced tab) ── */
@media (min-width: 1024px) { .tb-hide-desktop { display: none !important; } }
@media (min-width: 768px) and (max-width: 1023px) { .tb-hide-tablet { display: none !important; } }
@media (max-width: 767px) { .tb-hide-mobile { display: none !important; } }

/* ── Entrance animations (TB3-05) ── */
@keyframes tb-fade-in    { from { opacity: 0; } to { opacity: 1; } }
@keyframes tb-slide-up   { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
@keyframes tb-slide-right{ from { opacity: 0; transform: translateX(-30px); } to { opacity: 1; transform: translateX(0); } }
@keyframes tb-zoom-in    { from { opacity: 0; transform: scale(0.88); } to { opacity: 1; transform: scale(1); } }
@keyframes tb-bounce-in  { 0% { opacity:0; transform:scale(0.3); } 50% { opacity:1; transform:scale(1.05); } 70% { transform:scale(0.9); } 100% { transform:scale(1); } }
.tb-animate-fade-in     { animation: tb-fade-in     var(--tb-animate-dur, 600ms) ease both; }
.tb-animate-slide-up    { animation: tb-slide-up    var(--tb-animate-dur, 600ms) ease both; }
.tb-animate-slide-right { animation: tb-slide-right var(--tb-animate-dur, 600ms) ease both; }
.tb-animate-zoom-in     { animation: tb-zoom-in     var(--tb-animate-dur, 600ms) ease both; }
.tb-animate-bounce-in   { animation: tb-bounce-in   var(--tb-animate-dur, 700ms) ease both; }

/* ── Inline editing (TB3-04) ── */
[data-tb-editable][contenteditable="true"] {
    outline: 2px dashed #6366f1;
    outline-offset: 2px;
    border-radius: 3px;
    cursor: text;
    min-width: 20px;
    min-height: 1em;
    padding: 2px 4px;
    white-space: pre-wrap;
}

/* ── TB4: Flex Container ── */
.tb-flex-container { width: 100%; }
.tb-fc-empty {
    display: flex; align-items: center; justify-content: center;
    min-height: 80px; border: 2px dashed #c7d2fe; border-radius: 8px;
    color: #818cf8; font-size: 0.8rem; font-weight: 500;
    background: rgba(99,102,241,0.04); width: 100%;
}

/* ── TB4: Nested block highlight (selected child inside container) ── */
.tb-canvas-block[data-tb-container="1"] > .tb-canvas-block:hover {
    outline: 2px solid rgba(99,102,241,0.45);
}
.tb-canvas-block[data-tb-container="1"].tb-selected {
    outline: 2px solid #10b981;
    box-shadow: 0 0 0 4px rgba(16,185,129,0.12);
}

/* ── TB4: Container drop zone during drag ── */
.tb-canvas-block[data-tb-container="1"].tb-container-drag-over {
    outline: 2px dashed #6366f1 !important;
    background: rgba(99,102,241,0.04) !important;
}

/* ── Module placeholder (admin canvas, no live data) ── */
.tb-module-placeholder {
    background: linear-gradient(135deg, #f0f4ff 0%, #e8effe 100%);
    border: 2px dashed #a5b4fc;
    border-radius: 0.75rem;
    padding: 2.5rem 2rem;
    text-align: center;
    color: #6366f1;
    font-size: 0.95rem;
    font-weight: 600;
}
.tb-module-placeholder .tb-module-icon { font-size: 2rem; margin-bottom: 0.5rem; }

{!! $css !!}
</style>
@foreach(($extAssets['css'] ?? []) as $__extHref)
<link rel="stylesheet" href="{{ $__extHref }}">
@endforeach
</head>
<body>

@if(empty($blocks))
<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:80vh;text-align:center;color:#9ca3af;padding:2rem;">
    <div style="font-size:3.5rem;margin-bottom:1rem;opacity:0.4;">🧩</div>
    <p style="font-size:1rem;font-weight:600;margin:0 0 0.5rem;">صفحه هنوز بلوکی ندارد</p>
    <p style="font-size:0.85rem;margin:0;">از کتابخانه بلوک بکشید یا روی آن کلیک کنید</p>
</div>
@else

{{-- First drop zone (before first block) --}}
<div class="tb-drop-zone"
     data-before="{{ $blocks[0]['id'] ?? '__end__' }}"
     data-index="0"></div>

@foreach($blocks as $index => $block)
@php
    $bid = $block['id'] ?? '';
    $nextBlock = $blocks[$index + 1] ?? null;
@endphp
{{-- Rendered block (already wrapped in .tb-canvas-block by BlockRenderer editor_mode) --}}
{!! $renderedBlocks[$bid] ?? '' !!}

{{-- Drop zone after this block --}}
<div class="tb-drop-zone"
     data-before="{{ $nextBlock['id'] ?? '__end__' }}"
     data-index="{{ $index + 1 }}"></div>
@endforeach

@endif

@foreach(($extAssets['js'] ?? []) as $__extSrc)
<script src="{{ $__extSrc }}" defer></script>
@endforeach

@include('theme-runtime::canvas.editor-bootstrap', ['canvasStructuralServices' => ['admin.theme-builder.partials.canvas-structural-services']])
@include('theme-runtime::canvas.inspect')
<script>
(function () {
    'use strict';

    var state = {
        selectedBlockId: null,
        dragActive: false
    };

    function notifyParent(msg) {
        try { ThemeCanvasRuntime.post(parent, msg.action, Object.fromEntries(Object.entries(msg).filter(([key]) => key !== 'action'))); } catch (_) {}
    }

    /* ── TB3-04: Inline editing ──────────────────────────────────── */
    var inlineState = { editing: false, propKey: null, inlineType: null, debounceTimer: null };

    function enableInlineEditing(editableEl) {
        if (inlineState.editing && inlineState.el === editableEl) return;
        disableInlineEditing();
        inlineState.editing   = true;
        inlineState.el        = editableEl;
        inlineState.propKey   = editableEl.getAttribute('data-tb-editable');
        inlineState.inlineType = editableEl.getAttribute('data-tb-inline-type') || 'text';
        editableEl.contentEditable = 'true';
        editableEl.setAttribute('spellcheck', 'false');
        // For plain text, prevent rich paste
        if (inlineState.inlineType === 'text') {
            editableEl.addEventListener('paste', onInlinePaste, { once: false });
        }
        editableEl.addEventListener('input',   onInlineInput);
        editableEl.addEventListener('blur',    onInlineBlur);
        editableEl.addEventListener('keydown', onInlineKeydown);
        // Place cursor at end
        var sel = window.getSelection();
        var range = document.createRange();
        range.selectNodeContents(editableEl);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
    }

    function disableInlineEditing() {
        if (!inlineState.el) return;
        var el = inlineState.el;
        el.contentEditable = 'false';
        el.removeEventListener('input',   onInlineInput);
        el.removeEventListener('blur',    onInlineBlur);
        el.removeEventListener('keydown', onInlineKeydown);
        el.removeEventListener('paste',   onInlinePaste);
        inlineState.editing = false;
        inlineState.el      = null;
    }

    function onInlinePaste(e) {
        e.preventDefault();
        var text = (e.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
    }

    function onInlineInput() {
        clearTimeout(inlineState.debounceTimer);
        inlineState.debounceTimer = setTimeout(function () {
            sendInlineChange(false);
        }, 250);
    }

    function onInlineBlur() {
        clearTimeout(inlineState.debounceTimer);
        sendInlineChange(true);
        disableInlineEditing();
    }

    function onInlineKeydown(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            disableInlineEditing();
        } else if (e.key === 'Enter' && inlineState.inlineType === 'text') {
            e.preventDefault();
            clearTimeout(inlineState.debounceTimer);
            sendInlineChange(true);
            disableInlineEditing();
        }
    }

    function sendInlineChange(isFinal) {
        if (!inlineState.el) return;
        var val = inlineState.inlineType === 'html'
            ? inlineState.el.innerHTML
            : inlineState.el.textContent;
        notifyParent({ action: 'inlinePropChange', propKey: inlineState.propKey, value: val, final: !!isFinal });
    }

    /* ── Block click → select (or inline edit on double-click) ─── */
    document.addEventListener('dblclick', function (e) {
        var editableEl = e.target.closest('[data-tb-editable]');
        if (editableEl) {
            e.stopPropagation();
            enableInlineEditing(editableEl);
        }
    }, true);

    document.addEventListener('click', function (e) {
        // If clicking on active contenteditable, don't re-select block
        if (inlineState.editing && e.target.closest('[data-tb-editable][contenteditable="true"]')) return;
        // If clicking outside editable while editing, blur it
        if (inlineState.editing && !e.target.closest('[data-tb-editable]')) {
            disableInlineEditing();
        }
        var blockEl = e.target.closest('[data-tb-block-id]');
        if (blockEl) {
            e.stopPropagation();
            selectBlock(blockEl.getAttribute('data-tb-block-id'));
            notifyParent({ action: 'blockSelected', blockId: state.selectedBlockId });
        }
    }, true);

    /* ── Selection ────────────────────────────────────────────── */
    function selectBlock(blockId) {
        var prev = document.querySelector('.tb-canvas-block.tb-selected');
        if (prev) prev.classList.remove('tb-selected');
        state.selectedBlockId = blockId;
        if (blockId) {
            var el = document.querySelector('[data-tb-block-id="' + blockId + '"]');
            if (el) {
                el.classList.add('tb-selected');
                el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    }

    /* ── Block HTML update (live prop edit) ───────────────────── */
    function updateBlockHtml(blockId, html) {
        disableInlineEditing();
        ThemeCanvasRuntime.replaceNode(blockId, html);
        // A replaced subtree loses its measured handles.
        requestAnimationFrame(syncColumnResizers);
    }

    /* ── Drop zone helpers (TB3-03 + TB4) ────────────────────── */
    function setDropTarget(beforeId) {
        document.querySelectorAll('.tb-drop-zone').forEach(function (el) {
            el.classList.toggle('tb-drop-active', el.getAttribute('data-before') === beforeId);
        });
    }

    // TB4: highlight a container as the drag target
    var currentContainerTarget = null;
    function setContainerTarget(containerId) {
        if (currentContainerTarget) {
            currentContainerTarget.classList.remove('tb-container-drag-over');
            currentContainerTarget = null;
        }
        if (containerId) {
            var el = document.querySelector('[data-tb-block-id="' + containerId + '"][data-tb-container="1"]');
            if (el) { el.classList.add('tb-container-drag-over'); currentContainerTarget = el; }
        }
    }

    // Phase 2C pointer drag uses rendered iframe geometry as the sole drop
    // projection authority. Canonical parent/index validation still happens
    // in the editor service and again on the server.
    var pointerDrag = null;
    var structuralDropEl = null;
    function clearStructuralDrop() {
        if (structuralDropEl) structuralDropEl.classList.remove('tb-drop-before', 'tb-drop-after', 'tb-drop-inside', 'tb-drop-invalid');
        structuralDropEl = null;
    }
    function nearestCanonicalParent(el) {
        var parent = el.parentElement;
        while (parent) {
            if (parent.matches?.('[data-tb-block-id]')) return parent;
            parent = parent.parentElement;
        }
        return null;
    }
    function canonicalSiblings(parentEl) {
        return Array.from(document.querySelectorAll('[data-tb-block-id]')).filter(function (candidate) {
            return nearestCanonicalParent(candidate) === parentEl;
        });
    }
    function structuralCandidate(x, y) {
        var hit = document.elementFromPoint(x, y)?.closest?.('[data-tb-block-id]');
        if (!hit || !pointerDrag) return null;
        var dragged = document.querySelector('[data-tb-block-id="' + CSS.escape(pointerDrag.nodeId) + '"]');
        var rect = hit.getBoundingClientRect();
        var parentEl = nearestCanonicalParent(hit);
        var parentStyle = parentEl ? getComputedStyle(parentEl) : getComputedStyle(document.body);
        var horizontal = parentStyle.display.includes('flex') && parentStyle.flexDirection.startsWith('row');
        var position = CanvasDragDrop.resolve(rect, x, y, document.documentElement.dir || 'rtl', hit.dataset.tbContainer === '1', horizontal);
        var invalid = hit === dragged || !!dragged?.contains(hit);
        var targetParentId;
        var targetIndex;
        if (position === 'inside') {
            targetParentId = hit.dataset.tbBlockId;
            targetIndex = canonicalSiblings(hit).length;
        } else {
            targetParentId = parentEl?.dataset.tbBlockId || 'page';
            var siblings = canonicalSiblings(parentEl);
            targetIndex = Math.max(0, siblings.indexOf(hit)) + (position === 'after' ? 1 : 0);
        }
        return { hit, invalid, nodeId: pointerDrag.nodeId, targetParentId, targetIndex, position };
    }
    document.addEventListener('pointerdown', function (event) {
        var handle = event.target.closest('.tb-drag-handle');
        if (!handle || event.button !== 0) return;
        var block = handle.closest('[data-tb-block-id]');
        if (!block) return;
        event.preventDefault();
        handle.setPointerCapture?.(event.pointerId);
        pointerDrag = { pointerId: event.pointerId, nodeId: block.dataset.tbBlockId, candidate: null };
        document.body.classList.add('tb-pointer-dragging');
        notifyParent({ action: 'dragStart', nodeId: pointerDrag.nodeId });
    }, true);
    document.addEventListener('pointermove', function (event) {
        if (!pointerDrag || event.pointerId !== pointerDrag.pointerId) return;
        event.preventDefault();
        if (event.clientY < 48) window.scrollBy({ top: -18, behavior: 'auto' });
        else if (event.clientY > window.innerHeight - 48) window.scrollBy({ top: 18, behavior: 'auto' });
        var candidate = structuralCandidate(event.clientX, event.clientY);
        clearStructuralDrop();
        pointerDrag.candidate = candidate;
        if (!candidate) return;
        structuralDropEl = candidate.hit;
        structuralDropEl.classList.add(candidate.invalid ? 'tb-drop-invalid' : 'tb-drop-' + candidate.position);
        notifyParent({ action: 'dragOver', nodeId: candidate.nodeId, targetParentId: candidate.targetParentId, targetIndex: candidate.targetIndex, position: candidate.position });
    }, true);
    function finishPointerDrag(event, cancelled) {
        if (!pointerDrag || event.pointerId !== pointerDrag.pointerId) return;
        var active = pointerDrag;
        pointerDrag = null;
        document.body.classList.remove('tb-pointer-dragging');
        clearStructuralDrop();
        if (!cancelled && active.candidate && !active.candidate.invalid) notifyParent({
            action: 'dropNode', nodeId: active.candidate.nodeId,
            targetParentId: active.candidate.targetParentId,
            targetIndex: active.candidate.targetIndex,
            position: active.candidate.position
        });
        else notifyParent({ action: 'dragCancel', nodeId: active.nodeId });
    }
    document.addEventListener('pointerup', function (event) { finishPointerDrag(event, false); }, true);
    document.addEventListener('pointercancel', function (event) { finishPointerDrag(event, true); }, true);

    /* ── Column resizers (2026-11-10) ──────────────────────────
       The row is a CSS grid. Each handle sits on a track boundary and, while
       dragged, rewrites the row's inline grid-template-columns so the preview
       follows the pointer without a server round-trip. Releasing posts the
       final percentages to the editor, which persists them as
       props.columns[i].width. Disabled below 768px (the row stacks). */
    var COLUMN_MIN_PX = 100;
    var COLUMN_MIN_FRACTION = 0.1;
    var columnResize = null;

    function columnCells(colsEl) {
        return Array.prototype.filter.call(colsEl.children, function (el) {
            return el.nodeType === 1 && !el.matches('style, link, script, .tb-col-splitters');
        });
    }

    function columnsBlockId(colsEl) {
        var block = colsEl.closest('[data-tb-block-id]');
        return block ? block.getAttribute('data-tb-block-id') : null;
    }

    function applyColumnWidths(colsEl, widths) {
        var total = widths.reduce(function (sum, width) { return sum + width; }, 0);
        if (!total) return;
        var count = widths.length;
        colsEl.style.gridTemplateColumns = widths.map(function (width) {
            var weight = Math.round((width / total) * count * 1000) / 1000;
            return 'minmax(0,' + weight + 'fr)';
        }).join(' ');
    }

    // Inspector → Canvas: a number/slider edit applies the same live track
    // rewrite a drag does, without waiting for the debounced re-render.
    function setBlockColumnWidths(blockId, columns) {
        var colsEl = document.getElementById('tb-' + blockId);
        if (!colsEl || !colsEl.classList.contains('tb-columns')) {
            var block = document.querySelector('[data-tb-block-id="' + blockId.replace(/"/g, '\\"') + '"]');
            colsEl = block ? block.querySelector('.tb-columns') : null;
        }
        if (!colsEl) return;
        var cells = columnCells(colsEl);
        if (!cells.length) return;
        var widths = columns.map(function (column) { return Number(column && column.width); })
            .filter(function (width) { return Number.isFinite(width) && width > 0; });
        // A count mismatch means the two documents disagree; the next block
        // re-render is authoritative, so leave this frame alone.
        if (widths.length !== cells.length) return;
        applyColumnWidths(colsEl, widths);
        syncColumnResizers();
    }

    function roundedColumnPercents(widths) {
        var total = widths.reduce(function (sum, width) { return sum + width; }, 0);
        if (!total) return [];
        var percents = widths.map(function (width) {
            return Math.round((width / total) * 1000) / 10;
        });
        var drift = Math.round((100 - percents.reduce(function (sum, value) { return sum + value; }, 0)) * 10) / 10;
        percents[percents.length - 1] = Math.round((percents[percents.length - 1] + drift) * 10) / 10;
        return percents;
    }

    // Handles are re-measured from the live DOM, so they stay correct after
    // block replacement, image loading, font swap and window resizes.
    function syncColumnResizers() {
        document.querySelectorAll('.tb-columns[data-tb-resizable="1"]').forEach(function (colsEl) {
            var cells = columnCells(colsEl);
            var overlay = colsEl.querySelector(':scope > .tb-col-splitters');
            if (cells.length < 2) {
                if (overlay) overlay.remove();
                return;
            }
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'tb-col-splitters';
                overlay.setAttribute('aria-hidden', 'true');
                colsEl.appendChild(overlay);
            }
            var wanted = cells.length - 1;
            while (overlay.children.length > wanted) overlay.removeChild(overlay.lastChild);
            while (overlay.children.length < wanted) {
                var handle = document.createElement('button');
                handle.type = 'button';
                handle.className = 'tb-col-resizer';
                handle.setAttribute('role', 'separator');
                handle.setAttribute('aria-orientation', 'vertical');
                handle.setAttribute('aria-label', 'تغییر عرض ستون');
                handle.title = 'بکشید تا عرض ستون تغییر کند · دوبار کلیک برای عرض مساوی';
                overlay.appendChild(handle);
            }

            var box = colsEl.getBoundingClientRect();
            var rtl = (document.documentElement.dir || '').toLowerCase() === 'rtl';
            for (var index = 1; index < cells.length; index++) {
                var previous = cells[index - 1].getBoundingClientRect();
                var current = cells[index].getBoundingClientRect();
                var boundary = rtl
                    ? (previous.left + current.right) / 2
                    : (previous.right + current.left) / 2;
                overlay.children[index - 1].style.left = (boundary - box.left) + 'px';
            }
        });
    }

    function finishColumnResize(event, cancelled) {
        if (!columnResize || event.pointerId !== columnResize.pointerId) return;
        var active = columnResize;
        columnResize = null;
        document.body.classList.remove('tb-col-resizing');
        if (cancelled) {
            active.colsEl.style.gridTemplateColumns = '';
            syncColumnResizers();
            return;
        }
        commitColumnWidths(active.colsEl, active.widths);
    }

    function commitColumnWidths(colsEl, widths) {
        var blockId = columnsBlockId(colsEl);
        var percents = roundedColumnPercents(widths);
        if (!blockId || percents.length < 2) {
            colsEl.style.gridTemplateColumns = '';
            syncColumnResizers();
            return;
        }
        applyColumnWidths(colsEl, widths);
        syncColumnResizers();
        notifyParent({
            action: 'columnResize',
            blockId: blockId,
            columns: percents.map(function (width) { return { width: width }; })
        });
    }

    document.addEventListener('pointerdown', function (event) {
        var handle = event.target.closest('.tb-col-resizer');
        if (!handle || event.button !== 0) return;
        if (window.innerWidth <= 768) return;
        var colsEl = handle.closest('.tb-columns');
        if (!colsEl) return;
        var cells = columnCells(colsEl);
        if (cells.length < 2) return;

        event.preventDefault();
        event.stopPropagation();
        handle.setPointerCapture?.(event.pointerId);

        var rtl = (document.documentElement.dir || '').toLowerCase() === 'rtl';
        var index = Array.prototype.indexOf.call(handle.parentElement.children, handle);
        var rects = cells.map(function (cell) {
            var rect = cell.getBoundingClientRect();
            return rect.width;
        });
        columnResize = {
            pointerId: event.pointerId,
            colsEl: colsEl,
            startX: event.clientX,
            rowWidth: colsEl.getBoundingClientRect().width,
            widths: rects,
            leftIndex: rtl ? index + 1 : index,
            rightIndex: rtl ? index : index + 1
        };
        document.body.classList.add('tb-col-resizing');
    }, true);

    document.addEventListener('pointermove', function (event) {
        if (!columnResize || event.pointerId !== columnResize.pointerId) return;
        event.preventDefault();
        var delta = event.clientX - columnResize.startX;
        var pair = columnResize.widths[columnResize.leftIndex] + columnResize.widths[columnResize.rightIndex];
        var minimum = Math.max(COLUMN_MIN_PX, columnResize.rowWidth * COLUMN_MIN_FRACTION);
        minimum = Math.min(minimum, pair / 2);
        var left = columnResize.widths[columnResize.leftIndex] + delta;
        var right = columnResize.widths[columnResize.rightIndex] - delta;
        left = Math.max(minimum, Math.min(pair - minimum, left));
        right = pair - left;
        var widths = columnResize.widths.slice();
        widths[columnResize.leftIndex] = left;
        widths[columnResize.rightIndex] = right;
        columnResize.liveWidths = widths;
        applyColumnWidths(columnResize.colsEl, widths);
        syncColumnResizers();
    }, true);

    document.addEventListener('pointerup', function (event) {
        if (!columnResize || event.pointerId !== columnResize.pointerId) return;
        if (columnResize.liveWidths) columnResize.widths = columnResize.liveWidths;
        finishColumnResize(event, false);
    }, true);
    document.addEventListener('pointercancel', function (event) { finishColumnResize(event, true); }, true);

    // Keyboard: arrows resize by 2% of the row; Home/Enter/Space distribute;
    // double-click also distributes. Keyboard distribution is its own committed
    // edit so Ctrl+Z returns to the layout before the shortcut.
    document.addEventListener('keydown', function (event) {
        var handle = event.target.closest && event.target.closest('.tb-col-resizer');
        if (!handle) return;
        var colsEl = handle.closest('.tb-columns');
        if (!colsEl) return;
        var cells = columnCells(colsEl);
        if (cells.length < 2) return;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            var rtl = (document.documentElement.dir || '').toLowerCase() === 'rtl';
            var index = Array.prototype.indexOf.call(handle.parentElement.children, handle);
            var rects = cells.map(function (cell) { return cell.getBoundingClientRect().width; });
            var pair = rects[rtl ? index + 1 : index] + rects[rtl ? index : index + 1];
            var rowRect = colsEl.getBoundingClientRect();
            var minimum = Math.min(Math.max(COLUMN_MIN_PX, rowRect.width * COLUMN_MIN_FRACTION), pair / 2);
            var minimumPercent = pair > 0 ? (minimum / pair) * 100 : 5;
            var currentIndex = rtl ? index + 1 : index;
            var currentPercent = pair > 0 ? (rects[currentIndex] / pair) * 100 : 50;
            var step = 2;
            var delta = event.key === 'ArrowRight' ? step : -step;
            if (rtl) delta = -delta;
            var newPercent = Math.max(minimumPercent, Math.min(100 - minimumPercent, currentPercent + delta));
            var widths = rects.slice();
            widths[currentIndex] = pair * (newPercent / 100);
            widths[rtl ? index : index + 1] = pair - widths[currentIndex];
            commitColumnWidths(colsEl, widths);
        } else if (event.key === 'Home' || event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            var count = cells.length;
            commitColumnWidths(colsEl, new Array(count).fill(100 / count));
        }
    }, true);

    document.addEventListener('dblclick', function (event) {
        var handle = event.target.closest && event.target.closest('.tb-col-resizer');
        if (!handle) return;
        var colsEl = handle.closest('.tb-columns');
        if (!colsEl) return;
        var count = columnCells(colsEl).length;
        if (count < 2) return;
        event.preventDefault();
        event.stopPropagation();
        commitColumnWidths(colsEl, new Array(count).fill(100 / count));
    }, true);

    // TB4: only searches top-level blocks (direct children of body)
    function getTopLevelBlocks() {
        return Array.from(document.body.querySelectorAll(':scope > [data-tb-block-id], :scope > .tb-style-wrap > [data-tb-block-id]'));
    }

    function getDropIndexAtY(clientY) {
        var blocks = getTopLevelBlocks();
        var result = { index: blocks.length, beforeId: '__end__', containerId: null };
        for (var i = 0; i < blocks.length; i++) {
            var rect = blocks[i].getBoundingClientRect();
            if (clientY < rect.top + rect.height / 2) {
                result = { index: i, beforeId: blocks[i].getAttribute('data-tb-block-id'), containerId: null };
                break;
            }
        }
        return result;
    }

    // TB4: find the innermost container block under the cursor
    function getContainerAtPoint(x, y) {
        var els = document.elementsFromPoint(x, y);
        for (var i = 0; i < els.length; i++) {
            var el = els[i].closest('[data-tb-container="1"]');
            if (el) return el.getAttribute('data-tb-block-id');
        }
        return null;
    }

    /* ── Height notification ──────────────────────────────────── */
    function sendHeight() {
        notifyParent({ action: 'iframeHeight', height: Math.max(200, document.body.scrollHeight) });
    }

    /* ── postMessage bridge (parent → iframe) ─────────────────── */
    window.addEventListener('message', function (e) {
        if (!ThemeCanvasRuntime.sameOrigin(e.origin) || e.source !== parent) return;
        var d = e.data;
        if (!ThemeCanvasRuntime.valid(d, 'to-preview')) return;

        switch (d.action) {
            case 'select':
                selectBlock(d.blockId);
                break;
            case 'updateBlock':
                updateBlockHtml(d.blockId, d.html || '');
                sendHeight();
                break;
            case 'setProp':
                try {
                    if (typeof d.blockId !== 'string' || d.blockId === ''
                        || typeof d.propKey !== 'string' || d.propKey === '') {
                        throw new TypeError('setProp requires non-empty blockId and propKey strings');
                    }
                    if (!window.ThemeBlockSDK || typeof window.ThemeBlockSDK.setProp !== 'function') {
                        throw new TypeError('ThemeBlockSDK.setProp is unavailable');
                    }
                    window.ThemeBlockSDK.setProp(d.blockId, d.propKey, d.value);
                } catch (error) {
                    console.error('[CanvasPreview] setProp failed:', error);
                }
                break;
            case 'setColumnWidths':
                setBlockColumnWidths(d.blockId, Array.isArray(d.columns) ? d.columns : []);
                break;
            case 'showDropZones':
                document.body.classList.toggle('tb-drag-active', !!d.visible);
                if (!d.visible) { setDropTarget(null); setContainerTarget(null); }
                break;
            case 'setDropTarget':
                setDropTarget(d.beforeId || null);
                break;
            // TB4: highlight container for drag-into
            case 'setContainerTarget':
                setContainerTarget(d.containerId || null);
                break;
            case 'setStructuralDrop':
                if (structuralDropEl && !d.valid) {
                    structuralDropEl.classList.remove('tb-drop-before', 'tb-drop-after', 'tb-drop-inside');
                    structuralDropEl.classList.add('tb-drop-invalid');
                }
                break;
            case 'getDropIndex':
                var clientY = d.clientY || 0;
                var clientX = d.clientX || 0;
                var res = getDropIndexAtY(clientY);
                // TB4: also check if hovering a container
                var cid = getContainerAtPoint(clientX, clientY);
                notifyParent({ action: 'dropIndexResult', index: res.index, beforeId: res.beforeId, containerId: cid });
                break;
        }
    });

    /* ── Signal ready + initial height ───────────────────────── */
    if (document.readyState === 'complete') {
        sendHeight();
        syncColumnResizers();
    } else {
        window.addEventListener('load', function () { sendHeight(); syncColumnResizers(); });
    }
    notifyParent({ action: 'ready' });

    // Also watch for DOM resizes (images loading, fonts, block replacement)
    if (typeof ResizeObserver !== 'undefined') {
        var columnSyncFrame = 0;
        new ResizeObserver(function () {
            sendHeight();
            if (columnSyncFrame) return;
            columnSyncFrame = requestAnimationFrame(function () {
                columnSyncFrame = 0;
                syncColumnResizers();
            });
        }).observe(document.body);
    }
    window.addEventListener('resize', syncColumnResizers);
}());
</script>
</body>
</html>
