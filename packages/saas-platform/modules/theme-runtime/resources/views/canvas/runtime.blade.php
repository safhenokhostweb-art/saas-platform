<script>
(function (window, document) {
    'use strict';
    if (window.ThemeCanvasRuntime?.version === 1) return;

    const TO_PREVIEW = new Set(@json(\SaaSPlatform\ThemeRuntime\Canvas\PreviewProtocol::TO_PREVIEW));
    const TO_EDITOR = new Set(@json(\SaaSPlatform\ThemeRuntime\Canvas\PreviewProtocol::TO_EDITOR));
    const handlers = new Map();

    function sameOrigin(origin) { return origin === window.location.origin; }
    function message(action, payload) { return Object.assign({ protocolVersion: 1, action }, payload || {}); }
    function valid(data, direction) {
        if (!data || typeof data !== 'object' || (data.protocolVersion ?? 1) !== 1 || typeof data.action !== 'string') return false;
        if (!(direction === 'to-preview' ? TO_PREVIEW : TO_EDITOR).has(data.action)) return false;
        switch (data.action) {
            case 'select': return data.blockId == null || typeof data.blockId === 'string';
            case 'updateBlock': return typeof data.blockId === 'string' && typeof data.html === 'string';
            case 'blockSelected': return typeof data.blockId === 'string';
            case 'showDropZones': return typeof data.visible === 'boolean';
            case 'setDropTarget': return data.beforeId == null || typeof data.beforeId === 'string';
            case 'setContainerTarget': return data.containerId == null || typeof data.containerId === 'string';
            case 'setStructuralDrop': return (data.targetId == null || typeof data.targetId === 'string')
                && (data.position == null || ['before', 'after', 'inside'].includes(data.position))
                && typeof data.valid === 'boolean';
            case 'getDropIndex': return Number.isFinite(Number(data.clientY)) && Number.isFinite(Number(data.clientX));
            case 'iframeHeight': return Number.isFinite(Number(data.height));
            case 'inlinePropChange': return typeof data.propKey === 'string' && typeof data.final === 'boolean';
            case 'dragStart':
            case 'dragCancel': return typeof data.nodeId === 'string';
            case 'dragOver':
            case 'dropNode': return typeof data.nodeId === 'string'
                && typeof data.targetParentId === 'string'
                && Number.isInteger(data.targetIndex)
                && ['before', 'after', 'inside'].includes(data.position);
            default: return true;
        }
    }
    function register(id, lifecycle) {
        if (typeof id !== 'string' || !id || !lifecycle || typeof lifecycle !== 'object') throw new TypeError('Invalid lifecycle registration');
        handlers.set(id, lifecycle);
        if (document.readyState !== 'loading' && typeof lifecycle.mount === 'function') lifecycle.mount(document, { initial: true });
        return function () { handlers.delete(id); };
    }
    function lifecycle(phase, root, detail) {
        if (!['mount', 'update', 'refresh', 'destroy'].includes(phase)) throw new TypeError('Unknown lifecycle phase');
        handlers.forEach(function (handler) {
            if (typeof handler[phase] === 'function') handler[phase](root, detail || {});
        });
        if (phase === 'update' || phase === 'refresh') {
            document.dispatchEvent(new CustomEvent('theme:content-updated', { detail: Object.assign({ root }, detail || {}) }));
        }
        document.dispatchEvent(new CustomEvent('theme:canvas-lifecycle', { detail: Object.assign({ phase, root }, detail || {}) }));
    }
    function replaceNode(blockId, html) {
        if (typeof blockId !== 'string' || typeof html !== 'string') return null;
        const current = document.querySelector('[data-tb-block-id="' + CSS.escape(blockId) + '"]');
        if (!current) return null;
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const replacement = template.content.firstElementChild;
        if (!replacement || replacement.getAttribute('data-tb-block-id') !== blockId) return null;
        const selected = current.classList.contains('tb-selected');
        const scroll = { x: window.scrollX, y: window.scrollY };
        lifecycle('destroy', current, { blockId });
        current.replaceWith(replacement);
        if (selected) replacement.classList.add('tb-selected');
        lifecycle('update', replacement, { blockId });
        window.scrollTo(scroll.x, scroll.y);
        return replacement;
    }

    window.ThemeCanvasRuntime = Object.freeze({
        version: 1,
        sameOrigin,
        message,
        valid,
        register,
        lifecycle,
        replaceNode,
        post(target, action, payload) {
            if (target && typeof target.postMessage === 'function') target.postMessage(message(action, payload), window.location.origin);
        }
    });
    function mountDocument() { lifecycle('mount', document, { initial: true }); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountDocument, { once: true });
    else mountDocument();
}(window, document));
</script>
