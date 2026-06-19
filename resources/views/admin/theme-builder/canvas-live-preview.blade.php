<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Preview</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    direction: rtl;
    background: #ffffff;
    color: #1f2937;
    margin: 0;
    padding: 0;
    min-height: 100vh;
}
/* Base TB styles */
.tb-container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }
.tb-section { width: 100%; }
.tb-section-inner { width: 100%; }
.tb-padding-none  { padding-top: 0; padding-bottom: 0; }
.tb-padding-sm    { padding-top: 2rem; padding-bottom: 2rem; }
.tb-padding-md    { padding-top: 4rem; padding-bottom: 4rem; }
.tb-padding-lg    { padding-top: 6rem; padding-bottom: 6rem; }
.tb-padding-xl    { padding-top: 8rem; padding-bottom: 8rem; }
.tb-grid          { display: grid; gap: 1.5rem; }
.tb-cols-2        { grid-template-columns: repeat(2, 1fr); }
.tb-cols-3        { grid-template-columns: repeat(3, 1fr); }
.tb-cols-4        { grid-template-columns: repeat(4, 1fr); }
.tb-hero          { min-height: 420px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 5rem 1.5rem; }
.tb-hero h1       { font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3; }
.tb-hero p        { font-size: 1.1rem; opacity: 0.85; margin-bottom: 1.5rem; }
.tb-hero .tb-btn  { display: inline-flex; align-items: center; padding: 0.75rem 2rem; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.6); color: #fff; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; text-decoration: none; }
.tb-cta           { padding: 4rem 1.5rem; text-align: center; }
.tb-heading       { line-height: 1.4; margin: 0 0 1rem; font-weight: 700; }
.tb-text          { line-height: 1.85; color: #374151; margin: 0 0 1rem; }
.tb-img           { max-width: 100%; border-radius: 0.5rem; }
.tb-button        { display: inline-flex; align-items: center; padding: 0.6rem 1.5rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.9rem; text-decoration: none; }
.tb-btn-primary   { background: var(--tb-primary, #4f46e5); color: #fff; }
.tb-divider       { border: none; border-top: 1px solid #e5e7eb; margin: 1rem 0; }
.tb-bg-primary    { background: var(--tb-primary, #4f46e5); color: #fff; }
.tb-bg-dark       { background: #1e293b; color: #fff; }
.tb-bg-light      { background: #f8fafc; }
.tb-bg-gray       { background: #f1f5f9; }

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
     data-before="{{ $nextBlock ? $nextBlock['id'] : '__end__' }}"
     data-index="{{ $index + 1 }}"></div>
@endforeach

@endif

<script>
(function () {
    'use strict';

    var state = {
        selectedBlockId: null,
        dragActive: false
    };

    function notifyParent(msg) {
        try { parent.postMessage(msg, '*'); } catch (_) {}
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
        var el = document.querySelector('[data-tb-block-id="' + blockId + '"]');
        if (!el) return;
        var badge = el.querySelector('.tb-block-badge');
        el.innerHTML = html;
        if (badge) el.insertBefore(badge, el.firstChild);
        if (state.selectedBlockId === blockId) el.classList.add('tb-selected');
    }

    /* ── Drop zone helpers (TB3-03) ───────────────────────────── */
    function setDropTarget(beforeId) {
        document.querySelectorAll('.tb-drop-zone').forEach(function (el) {
            el.classList.toggle('tb-drop-active', el.getAttribute('data-before') === beforeId);
        });
    }

    function getDropIndexAtY(clientY) {
        var blocks = Array.from(document.querySelectorAll('[data-tb-block-id]'));
        var result = { index: blocks.length, beforeId: '__end__' };
        for (var i = 0; i < blocks.length; i++) {
            var rect = blocks[i].getBoundingClientRect();
            if (clientY < rect.top + rect.height / 2) {
                result = { index: i, beforeId: blocks[i].getAttribute('data-tb-block-id') };
                break;
            }
        }
        return result;
    }

    /* ── Height notification ──────────────────────────────────── */
    function sendHeight() {
        notifyParent({ action: 'iframeHeight', height: Math.max(200, document.body.scrollHeight) });
    }

    /* ── postMessage bridge (parent → iframe) ─────────────────── */
    window.addEventListener('message', function (e) {
        var d = e.data;
        if (!d || !d.action) return;

        switch (d.action) {
            case 'select':
                selectBlock(d.blockId);
                break;
            case 'updateBlock':
                updateBlockHtml(d.blockId, d.html || '');
                sendHeight();
                break;
            case 'showDropZones':
                document.body.classList.toggle('tb-drag-active', !!d.visible);
                if (!d.visible) setDropTarget(null);
                break;
            case 'setDropTarget':
                setDropTarget(d.beforeId || null);
                break;
            case 'getDropIndex':
                var res = getDropIndexAtY(d.clientY || 0);
                notifyParent({ action: 'dropIndexResult', index: res.index, beforeId: res.beforeId });
                break;
        }
    });

    /* ── Signal ready + initial height ───────────────────────── */
    notifyParent({ action: 'ready' });
    if (document.readyState === 'complete') {
        sendHeight();
    } else {
        window.addEventListener('load', sendHeight);
    }
    // Also watch for DOM resizes (images loading, etc.)
    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(sendHeight).observe(document.body);
    }
}());
</script>
</body>
</html>
