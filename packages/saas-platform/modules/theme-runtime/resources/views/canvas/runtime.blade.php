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
            case 'setProp': return typeof data.blockId === 'string' && data.blockId !== ''
                && typeof data.propKey === 'string' && data.propKey !== '';
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
            case 'setColumnWidths': return typeof data.blockId === 'string' && data.blockId !== ''
                && Array.isArray(data.columns) && data.columns.length >= 1
                && data.columns.every(function (column) { return column && Number.isFinite(Number(column.width)); });
            case 'columnResize': return typeof data.blockId === 'string' && data.blockId !== ''
                && Array.isArray(data.columns) && data.columns.length >= 2
                && data.columns.every(function (column) { return column && Number.isFinite(Number(column.width)); });
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
        if (phase === 'destroy' && root && typeof root.getAttribute === 'function') {
            cleanupElement(root);
        }
        handlers.forEach(function (handler) {
            if (typeof handler[phase] === 'function') handler[phase](root, detail || {});
        });
        if (phase === 'update' || phase === 'refresh') {
            document.dispatchEvent(new CustomEvent('theme:content-updated', { detail: Object.assign({ root }, detail || {}) }));
        }
        document.dispatchEvent(new CustomEvent('theme:canvas-lifecycle', { detail: Object.assign({ phase, root }, detail || {}) }));
    }
    const blockHandlers = new Map();
    const scriptLoaded = new Map();
    const scriptPromises = new Map();
    const eventListeners = new Map();
    const elementSubscriptions = new WeakMap();

    function eventBus() { return eventListeners; }

    function onEvent(eventName, handler) {
        if (typeof eventName !== 'string' || eventName === '') throw new TypeError('eventName must be a non-empty string');
        if (typeof handler !== 'function') throw new TypeError('handler must be a function');
        if (!eventListeners.has(eventName)) eventListeners.set(eventName, new Set());
        var set = eventListeners.get(eventName);
        set.add(handler);
        return function () { set.delete(handler); };
    }

    function offEvent(eventName, handler) {
        if (!eventListeners.has(eventName)) return;
        if (handler === undefined) { eventListeners.delete(eventName); return; }
        eventListeners.get(eventName).delete(handler);
        if (eventListeners.get(eventName).size === 0) eventListeners.delete(eventName);
    }

    function emitEvent(eventName, payload) {
        if (!eventListeners.has(eventName)) return;
        var set = eventListeners.get(eventName);
        set.forEach(function (handler) {
            try { handler(payload); } catch (e) {
                console.error('EventBus handler error [' + eventName + ']:', e);
            }
        });
    }

    function subscribeElement(el, eventName, handler) {
        var off = onEvent(eventName, handler);
        var subs = elementSubscriptions.get(el) || new Set();
        subs.add({ eventName: eventName, handler: handler, off: off });
        elementSubscriptions.set(el, subs);
        return off;
    }

    function cleanupElement(el) {
        var subs = elementSubscriptions.get(el);
        if (!subs) return;
        subs.forEach(function (sub) { sub.off(); });
        elementSubscriptions.delete(el);
    }

    function trackScript(src) {
        if (scriptPromises.has(src)) return scriptPromises.get(src);
        var promise = new Promise(function (resolve) {
            var s = document.querySelector('script[src="' + CSS.escape(src) + '"]');
            if (s && (s.complete || s.readyState === 'loaded')) { resolve(); scriptLoaded.set(src, true); }
            else {
                s = document.createElement('script');
                s.src = src;
                s.onload = function () { resolve(); scriptLoaded.set(src, true); };
                s.onerror = function () { resolve(); };
                s.async = true;
                document.body.appendChild(s);
            }
        });
        scriptPromises.set(src, promise);
        return promise;
    }

    function registerBlock(type, lifecycle) {
        if (typeof type !== 'string' || !type || !lifecycle || typeof lifecycle !== 'object') throw new TypeError('Invalid block lifecycle registration');
        blockHandlers.set(type, lifecycle);
        return function () { blockHandlers.delete(type); };
    }

    function initBlock(el) {
        var type = el.getAttribute('data-tb-block-type');
        if (!type) return false;
        var handler = blockHandlers.get(type);
        if (!handler || typeof handler.init !== 'function') return false;
        var propsAttr = el.getAttribute('data-tb-block-props');
        var props = {};
        if (propsAttr) { try { props = JSON.parse(propsAttr); } catch (e) { props = {}; } }
        var ctx = {
            element: el,
            type: type,
            blockId: el.getAttribute('data-tb-block-id'),
            props: props,
            on: function (eventName, handler) { return subscribeElement(el, eventName, handler); },
            off: function (eventName, handler) { if (handler !== undefined) { offEvent(eventName, handler); } },
            emit: emitEvent
        };
        try {
            var result = handler.init(ctx);
            if (result && typeof result.then === 'function') {
                result.then(function () { el.classList.add('tb-block-ready'); })
                    .catch(function (e) { console.error('Block init error [' + type + ']:', e); });
            } else {
                el.classList.add('tb-block-ready');
            }
        } catch (e) {
            console.error('Block init error [' + type + ']:', e);
        }
        return true;
    }

    // A block's markup may carry its own assets beside its root: nav_header
    // opens with a <style>. Those are never the block's root.
    function tagOf(el) { return String(el && el.tagName || '').toUpperCase(); }
    function isAsset(el) { return ['STYLE', 'SCRIPT', 'LINK', 'TEMPLATE'].includes(tagOf(el)); }
    // Assets that travel with the root on a live update: styles and
    // stylesheets (a <script> parsed from a template would never run).
    function isStyleAsset(el) { return tagOf(el) === 'STYLE' || (tagOf(el) === 'LINK' && el.getAttribute('rel') === 'stylesheet'); }

    function replaceNode(blockId, html) {
        if (typeof blockId !== 'string' || typeof html !== 'string') return null;
        const selector = '[data-tb-block-id="' + CSS.escape(blockId) + '"]';
        const hooked = Array.from(document.querySelectorAll(selector));
        const current = hooked.find((el) => !isAsset(el)) || hooked[0];
        if (!current) return null;
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const content = template.content;
        const nodes = content.children ? Array.from(content.children) : (content.firstElementChild ? [content.firstElementChild] : []);
        // The root is the first rendered element carrying the block id, not
        // simply the first element (which may be the block's own <style>).
        const replacement = nodes.find((el) => !isAsset(el) && el.getAttribute('data-tb-block-id') === blockId) || null;
        if (!replacement) return null;
        const at = nodes.indexOf(replacement);
        const leading = nodes.slice(0, at).filter(isStyleAsset);
        const trailing = nodes.slice(at + 1).filter(isStyleAsset);
        const selected = current.classList.contains('tb-selected');
        const scroll = { x: window.scrollX, y: window.scrollY };
        lifecycle('destroy', current, { blockId });
        if (leading.length || trailing.length) {
            // Retire the block's previous assets: the ones an earlier live
            // update tagged, else the untagged run the server rendered right
            // around the root (as many as the new markup brings).
            let stale = Array.from(document.querySelectorAll('[data-tb-block-asset="' + CSS.escape(blockId) + '"]'));
            if (!stale.length) {
                const run = (start, step, limit) => {
                    const out = [];
                    for (let el = start; el && out.length < limit && isStyleAsset(el) && !el.hasAttribute('data-tb-block-asset'); el = el[step]) out.push(el);
                    return out;
                };
                stale = run(current.previousElementSibling, 'previousElementSibling', leading.length)
                    .concat(run(current.nextElementSibling, 'nextElementSibling', trailing.length));
            }
            stale.filter((el) => el !== current).forEach((el) => el.remove());
            leading.forEach((el) => { el.setAttribute('data-tb-block-asset', blockId); current.before(el); });
        }
        current.replaceWith(replacement);
        let anchor = replacement;
        trailing.forEach((el) => { el.setAttribute('data-tb-block-asset', blockId); anchor.after(el); anchor = el; });
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
        registerBlock,
        initBlock,
        trackScript,
        lifecycle,
        replaceNode,
        events: {
            on: onEvent,
            off: offEvent,
            emit: emitEvent
        },
        post(target, action, payload) {
            if (target && typeof target.postMessage === 'function') target.postMessage(message(action, payload), window.location.origin);
        }
    });
    function mountDocument() { lifecycle('mount', document, { initial: true }); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountDocument, { once: true });
    else mountDocument();
}(window, document));
</script>
