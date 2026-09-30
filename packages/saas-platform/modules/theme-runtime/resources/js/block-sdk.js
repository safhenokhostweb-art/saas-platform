/*!
 * ThemeBlockSDK — the standardized Block SDK (Phase 9, Step 1).
 *
 * WHAT THIS IS
 *   A thin, additive convenience layer over `ThemeCanvasRuntime`. The runtime
 *   (protocol version 1) stays the single authority for the editor bridge: it
 *   owns `replaceNode`, the `lifecycle()` phase fan-out, the postMessage
 *   protocol, and the event bus. This SDK never re-implements any of it — it
 *   only registers blocks against the surfaces the runtime already exposes
 *   (`registerBlock`, `initBlock`, `trackScript`, `events.*`) and removes the
 *   boilerplate a block author would otherwise repeat in every block.
 *
 * IT DOES NOT CHANGE THE PROTOCOL
 *   `ThemeCanvasRuntime.version` stays 1. Dropping this file reverts the
 *   ecosystem to raw runtime access with no other change.
 *
 * THE ctx OBJECT (the documented developer surface)
 *   Built by the runtime (canvas/runtime.blade.php) and then DECORATED here.
 *   Runtime-owned, passed through untouched:
 *     ctx.element   HTMLElement        the block root element (never null)
 *     ctx.type      string             matches defineBlock({ type })
 *     ctx.blockId   string | null      from data-tb-block-id
 *     ctx.props     object             parsed data-tb-block-props
 *     ctx.on(event, fn)  () => void   element-scoped subscription; the runtime
 *                                    unsubscribes it on destroy via its
 *                                    WeakMap-backed elementSubscriptions
 *     ctx.off(event, fn) void
 *     ctx.emit(event, payload) void   global bus
 *   SDK-added (documented, stable):
 *     ctx.$      (selector) => Element[]  scoped query, never escapes element
 *     ctx.ready  (promise?) => Promise    resolves declared deps.js/deps.css
 *     ctx.setProp(key, value) void        LOCAL state only — see below
 *     ctx.emit   wrapped: name-validated, always stamps { blockId, type }
 *     ctx.block  object                   the block instance (`this` target)
 *     ctx.state  object                   per-instance mutable state bag
 *
 *   ctx.setProp is LOCAL BY CONTRACT: it updates ctx.state-derived props and
 *   re-serialises data-tb-block-props on the live element so a later re-init
 *   sees them, and emits `state:update`. It never round-trips through the
 *   editor's `inlinePropChange` action and never persists to a theme.
 *
 * DEPENDENCIES
 *   deps.js  -> resolved through the runtime's own `trackScript(src)`
 *               (dedupe by src, Promise, async) before the block body runs.
 *   deps.css -> the runtime has no stylesheet loader, so the SDK injects one
 *               <link> per href, deduped, and waits for load/error. A failing
 *               dependency resolves rather than rejecting: a block that cannot
 *               load an optional asset must still be able to render, and
 *               `block:error` carries the failure.
 *
 * LIFECYCLE
 *   init(ctx)        on the element, once per element (guarded).
 *   onUpdate(ctx, d) after a runtime `update` phase (element replaced). On
 *                    the real replace path the update phase fans out to the
 *                    NEW instance; with async init the SDK serialises phases
 *                    per instance (see gateHandler) so init's continuation
 *                    always completes before the next phase is delivered.
 *   onRefresh(ctx,d) after a runtime `refresh` phase (document-wide).
 *   onDestroy(ctx,d) during a runtime `destroy` phase. NOTE: the runtime
 *                    releases every ctx.on() subscription BEFORE destroy
 *                    handlers run (canvas/runtime.blade.php, cleanupElement
 *                    is called first), so onDestroy may not rely on ctx.on.
 *                    That WeakMap cleanup is the teardown mechanism; the SDK
 *                    holds no second subscription registry of its own.
 *                    A destroy that lands while an async init is still
 *                    pending is held back and delivered after init's
 *                    continuation — releaseInstance() can never overtake
 *                    onDestroy.
 *
 * EVENT SCHEMA
 *   Pattern: /^([a-z0-9_]+:)+[a-z0-9_]+$/ — at least one namespace segment.
 *   Block-local:  block:<type>:<verb>[:<detail>]   e.g. block:counter:change
 *   Cross-block:  state:update | state:hydrate | state:reset
 *   SDK-reserved: block:ready | block:update | block:refresh | block:destroy
 *                 | block:error
 *   Runtime-owned, never redefined here: theme:content-updated,
 *                 theme:canvas-lifecycle, theme:interaction
 *
 * DISTRIBUTION
 *   Plain distributable JavaScript with a CommonJS export, exactly like
 *   canvas-identity.js, so Node tests and Blade hosts consume one source.
 */
(function (root, factory) {
    'use strict';

    var mod = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = mod;
        return;
    }

    // `root.document` is what separates a real browser include from a Node
    // import of this same file: without it there is no canvas to attach to,
    // and create()'s fail-fast would turn every Node-side import of the
    // contract into a crash. In a browser the runtime contract is genuinely
    // required — include the runtime first, exactly as the editor bootstrap's
    // version gate demands.
    if (root && root.document) {
        root.ThemeBlockSDK = mod.create(root.ThemeCanvasRuntime, {
            document: root.document,
            window: root,
        });
    }
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';

    var VERSION = 1;

    var READY_ATTR = 'data-tb-block-init';
    var ERROR_ATTR = 'data-tb-block-error';
    var PROPS_ATTR = 'data-tb-block-props';
    var READY_CLASS = 'tb-block-ready';
    var LIFECYCLE_ID = 'theme-block-sdk';

    var PHASES = ['update', 'refresh', 'destroy'];

    var EVENT_PATTERN = /^[a-z0-9_]+(:[a-z0-9_]+)+$/;

    var EVENTS = Object.freeze({
        READY: 'block:ready',
        UPDATE: 'block:update',
        REFRESH: 'block:refresh',
        DESTROY: 'block:destroy',
        ERROR: 'block:error',
        STATE_UPDATE: 'state:update',
        STATE_HYDRATE: 'state:hydrate',
        STATE_RESET: 'state:reset',
    });

    // Emitted by the runtime itself; documented here so block authors know
    // they exist and must not redefine them.
    var RUNTIME_EVENTS = Object.freeze([
        'theme:content-updated',
        'theme:canvas-lifecycle',
        'theme:interaction',
    ]);

    function isPlainObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    function normalizeAssets(list) {
        if (!Array.isArray(list)) return [];
        var out = [];
        for (var i = 0; i < list.length; i++) {
            if (typeof list[i] === 'string' && list[i] !== '' && out.indexOf(list[i]) === -1) {
                out.push(list[i]);
            }
        }
        return Object.freeze(out);
    }

    function pickMethod(source, names) {
        for (var i = 0; i < names.length; i++) {
            if (typeof source[names[i]] === 'function') return source[names[i]];
        }
        return null;
    }

    function isElement(node) {
        return !!node && typeof node === 'object' && typeof node.getAttribute === 'function';
    }

    /**
     * A block base class. Subclass it when the block owns real state; use
     * defineBlock({ ... }) when a plain object literal is enough.
     */
    function BaseBlock(definition) {
        this.definition = definition;
        this.el = null;
        this.ctx = null;
        this.state = {};
    }

    BaseBlock.version = 1;
    BaseBlock.prototype.init = function () {};
    BaseBlock.prototype.onUpdate = function () {};
    BaseBlock.prototype.onRefresh = function () {};
    BaseBlock.prototype.onDestroy = function () {};

    /**
     * Build one SDK instance bound to a live ThemeCanvasRuntime.
     */
    function create(runtime, options) {
        options = options || {};

        if (!runtime || typeof runtime.registerBlock !== 'function'
            || typeof runtime.initBlock !== 'function'
            || typeof runtime.register !== 'function'
            || runtime.version !== 1) {
            throw new TypeError(
                'ThemeBlockSDK requires ThemeCanvasRuntime version 1 (found '
                + (runtime ? 'version ' + runtime.version : 'no runtime') + ' — include '
                + 'theme-runtime::canvas.runtime before the block SDK)'
            );
        }

        var doc = options.document
            || (typeof document !== 'undefined' ? document : null);
        var win = options.window
            || (typeof window !== 'undefined' ? window : null);
        var bus = runtime.events || {};

        var definitions = new Map();   // type -> normalized definition
        var instances = new Map();    // type -> Set<instance>
        var stylePromises = new Map();// href  -> Promise (dedupe)
        var warnedNames = new Set();  // one warning per non-namespaced event
        var lifecycleOff = null;

        function warn(message) {
            if (win && win.console && typeof win.console.warn === 'function') {
                win.console.warn('[BlockSDK] ' + message);
            }
        }

        function error(message, err) {
            if (win && win.console && typeof win.console.error === 'function') {
                win.console.error('[BlockSDK] ' + message, err);
            }
        }

        function busEmit(name, payload) {
            if (typeof bus.emit === 'function') bus.emit(name, payload);
        }

        // ---------------------------------------------------------------- deps

        function trackStyle(href) {
            if (stylePromises.has(href)) return stylePromises.get(href);

            var promise;
            if (!doc || typeof doc.createElement !== 'function') {
                promise = Promise.resolve(false);
            } else {
                promise = new Promise(function (resolve) {
                    var link = doc.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = href;
                    link.onload = function () { resolve(true); };
                    link.onerror = function () { resolve(false); };
                    if (doc.head && typeof doc.head.appendChild === 'function') {
                        doc.head.appendChild(link);
                    } else if (doc.body && typeof doc.body.appendChild === 'function') {
                        doc.body.appendChild(link);
                    } else {
                        resolve(false);
                    }
                });
            }

            stylePromises.set(href, promise);
            return promise;
        }

        function resolveStyles(definition) {
            var hrefs = definition.deps.css;
            if (hrefs.length === 0) return Promise.resolve([]);
            return Promise.all(hrefs.map(trackStyle));
        }

        function resolveScripts(definition) {
            if (typeof runtime.trackScript !== 'function') return Promise.resolve();
            return Promise.all(definition.deps.js.map(function (src) {
                return runtime.trackScript(src);
            }));
        }

        /**
         * Resolve declared dependencies, then any extra promise the caller
         * passes. A dependency that fails to load resolves (false) rather than
         * rejecting — an optional asset must not take the block down.
         */
        function readyFor(definition, extra) {
            var pending = Promise.all([resolveScripts(definition), resolveStyles(definition)]);
            if (!extra) return pending;
            return Promise.all([pending, extra]);
        }

        // ----------------------------------------------------------------- ctx

        function decorate(ctx, instance, definition) {
            // instance.props is the block's OWN mutable prop map. ctx.props is
            // a frozen published view of it, so a block can never mutate its
            // own configuration by accident — the only supported write path
            // is ctx.setProp(), which re-publishes the view and emits
            // state:update. Read ctx.props fresh; a previously captured
            // reference is a snapshot.
            var props = {};
            var key;
            for (key in definition.defaultProps) {
                if (Object.prototype.hasOwnProperty.call(definition.defaultProps, key)) {
                    props[key] = definition.defaultProps[key];
                }
            }
            for (key in ctx.props) {
                if (Object.prototype.hasOwnProperty.call(ctx.props, key)) props[key] = ctx.props[key];
            }
            instance.props = props;

            var scoped = Object.create(ctx);

            function publish() {
                scoped.props = Object.freeze(Object.assign({}, instance.props));
            }
            publish();

            scoped.block = instance;
            scoped.state = instance.state;

            scoped.$ = function (selector) {
                if (typeof selector !== 'string' || selector === '') return [];
                if (!ctx.element || typeof ctx.element.querySelectorAll !== 'function') return [];
                try {
                    return Array.prototype.slice.call(ctx.element.querySelectorAll(selector));
                } catch (e) {
                    return [];
                }
            };

            scoped.ready = function (promise) { return readyFor(definition, promise); };

            scoped.setProp = function (propKey, value) {
                if (typeof propKey !== 'string' || propKey === '') return;
                instance.props[propKey] = value;
                publish();
                if (isElement(ctx.element) && typeof ctx.element.setAttribute === 'function') {
                    try {
                        ctx.element.setAttribute(PROPS_ATTR, JSON.stringify(instance.props));
                    } catch (e) {
                        // A non-serialisable value is a block bug, not a
                        // reason to break the interaction that called it.
                        error('setProp could not serialise props for "' + definition.type + '"', e);
                    }
                }
                scoped.emit(EVENTS.STATE_UPDATE, { props: scoped.props });
            };

            scoped.emit = function (name, payload) {
                var eventName = name;
                if (typeof eventName !== 'string' || eventName === '') {
                    throw new TypeError('event name must be a non-empty string');
                }
                if (!EVENT_PATTERN.test(eventName)) {
                    if (!warnedNames.has(eventName)) {
                        warnedNames.add(eventName);
                        warn('event "' + eventName + '" is not namespaced — use '
                            + 'block:<type>:<verb> or state:<verb>');
                    }
                }
                var body = isPlainObject(payload) ? payload : {};
                body.blockId = ctx.blockId == null ? null : ctx.blockId;
                body.type = definition.type;
                busEmit(eventName, body);
            };

            return scoped;
        }

        // ------------------------------------------------------------ instances

        function instancesOf(type) {
            if (!instances.has(type)) instances.set(type, new Set());
            return instances.get(type);
        }

        function matches(instance, root, detail, phase) {
            if (instance.el === root) return true;
            // A refresh is document-wide by contract: there is no single
            // element to match against, so every live instance is reached.
            if (phase === 'refresh') return true;
            var blockId = detail && detail.blockId;
            if (blockId == null) return !root;
            return instance.blockId != null && instance.blockId === blockId;
        }

        function callHandler(instance, definition, methodName, detail) {
            var method = definition.methods[methodName];
            if (!method) return;
            try {
                method.call(instance, instance.ctx, detail || {});
            } catch (e) {
                error(methodName + ' failed for "' + definition.type + '"', e);
            }
        }

        /**
         * Serialise a block's lifecycle phases per instance (SDK-002).
         *
         * WHY THIS EXISTS
         *   init() is allowed to be async — it may return a promise (deps
         *   loading, an element probe) and finish later. But the phases that
         *   follow it are synchronous fan-outs from the runtime: the update
         *   phase runs IMMEDIATELY after initBlock() returns, i.e. before an
         *   async init's continuation has executed. Without ordering, the
         *   block receives onUpdate BEFORE init's own continuation, and
         *   whatever init assigns last silently wins — for the carousel that
         *   was its `state.handle`: init's deferred second initCarousel()
         *   hit the data-tb-carousel-init guard, returned null and OVERWROTE
         *   the good handle onUpdate had just assigned. The block then had no
         *   controller to destroy, so every replacement after the first
         *   leaked its window resize listener and autoplay timer (10
         *   replacements → 10 stranded listeners; see
         *   block-sdk-lifecycle.test.mjs).
         *
         * THE RULE
         *   A handler is dropped (not queued) when it arrives out of order:
         *     - an update/refresh that lands while an init is still pending
         *       is dropped — init's own continuation already represents the
         *       post-init state this update was about to produce, and the
         *       block's guard attributes make a re-run idempotent anyway;
         *     - a destroy that lands while init is pending is held until
         *       init's continuation has run, then delivered — a block must
         *       NEVER be released with its init body unexecuted, and
         *       releaseInstance() must stay ordered AFTER onDestroy
         *       completes (the structural invariant this file used to
         *       violate under this exact race).
         *
         *   This is a lifecycle-orchestration fix inside the SDK, not a
         *   block-level workaround: blocks keep writing plain
         *   `this.state.x = ...` in init and onUpdate.
 */
        function gateHandler(instance, methodName, deliver, detail) {
            var pending = instance.__pendingInit || null;

            if (methodName === 'onDestroy') {
                if (pending) {
                    // Hold destroy until init finishes, then release. The
                    // continuation callback flips the gate itself.
                    pending.destroyAfter = { deliver: deliver, detail: detail };
                    return;
                }
                deliver();
                return;
            }

            if (pending) {
                // Init still in flight: this phase describes state that does
                // not exist yet. Drop it — the init continuation is the
                // authoritative post-init run.
                return;
            }
            deliver();
        }

        /**
         * Wrap an init's returned promise (or a resolved one) so the per-
         * instance gate knows when init's continuation has really finished.
         * `onSettled` runs regardless of success or failure, AFTER the
         * block's own init continuation, and then delivers any destroy that
         * was held back by gateHandler.
         */
        function gateInit(instance, run, onSettled) {
            var pending = instance.__pendingInit || (instance.__pendingInit = {});
            return Promise.resolve(run).then(
                function () { onSettled(); finishPendingInit(instance); },
                function (e) { onSettled(); finishPendingInit(instance); throw e; }
            );
            function finishPendingInit(inst) {
                var p = inst.__pendingInit;
                inst.__pendingInit = null;
                if (p && p.destroyAfter) {
                    var held = p.destroyAfter;
                    p.destroyAfter = null;
                    held.deliver(held.detail);
                }
            }
        }

        function releaseInstance(instance, definition, detail) {
            if (instance.released) return;
            callHandler(instance, definition, 'onDestroy', detail);
            instance.released = true;
            if (isElement(instance.el) && typeof instance.el.removeAttribute === 'function') {
                instance.el.removeAttribute(READY_ATTR);
                instance.el.removeAttribute(ERROR_ATTR);
                if (instance.el.classList) {
                    instance.el.classList.remove(READY_CLASS);
                }
            }
            var set = instances.get(definition.type);
            if (set) set.delete(instance);
            if (set && set.size === 0) instances.delete(definition.type);
        }

        var HANDLER_FOR_PHASE = {
            update: 'onUpdate',
            refresh: 'onRefresh',
            destroy: 'onDestroy',
        };

        /**
         * Fan one runtime phase out to every live instance of every
         * registered definition. Deliveries are serialised per instance
         * through gateHandler so an async init can never interleave with a
         * later phase on the same instance.
         */

        function fanOut(phase, root, detail) {
            var handlerName = HANDLER_FOR_PHASE[phase];
            if (!handlerName) return;
            definitions.forEach(function (definition) {
                var set = instances.get(definition.type);
                if (!set || set.size === 0) return;
                // Array.from, never Array.prototype.slice.call: a Set has no
                // length, so slice silently yields an EMPTY array and the
                // phase would reach no block at all.
                Array.from(set).forEach(function (instance) {
                    if (!matches(instance, root, detail, phase)) return;
                    if (phase === 'destroy') {
                        // gateHandler holds this call while the instance's
                        // init is still pending and delivers it afterwards;
                        // ONLY THEN is the instance released — onDestroy
                        // always completes before the SDK loses its
                        // reference (releaseInstance).
                        gateHandler(instance, 'onDestroy', function () {
                            releaseInstance(instance, definition, detail);
                        }, detail);
                        return;
                    }
                    gateHandler(instance, handlerName, function () {
                        callHandler(instance, definition, handlerName, detail);
                    }, detail);
                });
            });
        }

        // ---------------------------------------------------------------- init

        function initBlock(type, runtimeCtx) {
            var definition = definitions.get(type);
            if (!definition) {
                warn('no block definition registered for type "' + type + '"');
                return undefined;
            }

            var el = runtimeCtx.element;
            if (isElement(el) && el.getAttribute(READY_ATTR) === String(definition.version)) {
                return undefined; // already initialised for this element
            }

            var instance = definition.ctor
                ? new definition.ctor(definition)
                : { definition: definition, state: {} };
            instance.definition = definition;
            instance.el = el;
            instance.blockId = runtimeCtx.blockId == null ? null : runtimeCtx.blockId;
            instance.released = false;
            instance.ctx = decorate(runtimeCtx, instance, definition);
            instance.ctx.state = instance.state;

            instancesOf(type).add(instance);

            if (isElement(el) && typeof el.setAttribute === 'function') {
                el.setAttribute(READY_ATTR, String(definition.version));
                if (typeof el.removeAttribute === 'function') el.removeAttribute(ERROR_ATTR);
            }

            var run;
            try {
                run = definition.methods.init ? definition.methods.init.call(instance, instance.ctx) : undefined;
            } catch (e) {
                failBlock(definition, instance, e);
                throw e;
            }

            if (run && typeof run.then === 'function') {
                // SDK-002: open the per-instance phase gate. gateInit runs
                // the init continuation to completion (or failure) BEFORE
                // any update/refresh/destroy phase this element receives is
                // delivered — the ordering that used to be left to chance.
                return gateInit(instance, run, function () {
                    if (instance.released) return;
                    if (instance.el && instance.el.classList) {
                        instance.el.classList.add(READY_CLASS);
                    }
                    busEmit(EVENTS.READY, {
                        blockId: instance.blockId,
                        type: definition.type,
                    });
                }).catch(function (e) {
                    failBlock(definition, instance, e);
                    throw e;
                });
            }

            if (instance.el && instance.el.classList) {
                instance.el.classList.add(READY_CLASS);
            }
            busEmit(EVENTS.READY, { blockId: instance.blockId, type: definition.type });
            return undefined;
        }

        function failBlock(definition, instance, err) {
            error('init failed for "' + definition.type + '"', err);
            if (isElement(instance.el) && typeof instance.el.setAttribute === 'function') {
                instance.el.setAttribute(ERROR_ATTR, '1');
                if (instance.el.classList) instance.el.classList.remove(READY_CLASS);
            }
            busEmit(EVENTS.ERROR, {
                blockId: instance.blockId,
                type: definition.type,
                error: err && err.message ? err.message : String(err),
            });
        }

        function scan(root) {
            var scope = root || doc;
            if (!scope || typeof scope.querySelectorAll !== 'function') return 0;
            var found = scope.querySelectorAll('[data-tb-block-type]');
            var started = 0;
            for (var i = 0; i < found.length; i++) {
                if (runtime.initBlock(found[i]) === true) started += 1;
            }
            return started;
        }

        // ------------------------------------------------------------- register

        function normalize(input, meta) {
            meta = meta || {};

            var source = typeof input === 'function' ? input.prototype : input;
            var ctor = typeof input === 'function' ? input : null;

            var type = meta.type
                || (typeof input === 'function' ? input.type : input.type)
                || null;
            if (typeof type !== 'string' || type === '') {
                throw new TypeError('defineBlock requires a non-empty "type"');
            }

            var deps = (meta.deps || (typeof input === 'function' ? input.deps : input.deps) || {});
            var defaultProps = meta.defaultProps
                || (typeof input === 'function' ? input.defaultProps : input.defaultProps)
                || {};

            if (!isPlainObject(deps)) throw new TypeError('defineBlock "deps" must be an object');
            if (!isPlainObject(defaultProps)) throw new TypeError('defineBlock "defaultProps" must be an object');

            var version = meta.version
                || (typeof input === 'function' ? input.version : input.version)
                || VERSION;

            var definition = {
                type: type,
                version: version,
                ctor: ctor,
                deps: Object.freeze({
                    js: normalizeAssets(deps.js),
                    css: normalizeAssets(deps.css),
                }),
                defaultProps: Object.freeze(Object.assign({}, defaultProps)),
                methods: {
                    init: pickMethod(source, ['init', 'mount']),
                    onUpdate: pickMethod(source, ['onUpdate', 'update']),
                    onRefresh: pickMethod(source, ['onRefresh', 'refresh']),
                    onDestroy: pickMethod(source, ['onDestroy', 'destroy']),
                },
                unregisterBlock: null,
            };

            if (definitions.has(type)) {
                warn('redefining block type "' + type + '" — the previous definition is replaced');
                definitions.get(type).unregisterBlock();
            }

            definitions.set(type, definition);
            definition.unregisterBlock = runtime.registerBlock(type, {
                init: function (ctx) { return initBlock(type, ctx); },
            });

            return definition;
        }

        function defineBlock(input, meta) {
            var definition = normalize(input, meta);
            return function unregister() {
                if (definitions.get(definition.type) === definition) {
                    Array.prototype.slice.call(instances.get(definition.type) || [])
                        .forEach(function (instance) {
                            releaseInstance(instance, definition, { reason: 'unregister' });
                        });
                    definition.unregisterBlock();
                    definitions.delete(definition.type);
                }
            };
        }

        function getBlockType(type) {
            var definition = definitions.get(type);
            if (!definition) return null;
            var set = instances.get(type);
            return Object.freeze({
                type: definition.type,
                version: definition.version,
                deps: definition.deps,
                instanceCount: set ? set.size : 0,
            });
        }

        function assetsFor(type) {
            var definition = definitions.get(type);
            if (!definition) return null;
            return definition.deps;
        }

        // ---------------------------------------------------------- lifecycle

        function onMount() {
            scan(doc);
        }

        function onUpdate(root, detail) {
            // A replaced element is a brand new node: bring it up through the
            // runtime's own init path, then notify the live instance.
            if (isElement(root) && typeof root.getAttribute === 'function'
                && root.getAttribute('data-tb-block-type')) {
                runtime.initBlock(root);
            }
            fanOut('update', root, detail);
            busEmit(EVENTS.UPDATE, { blockId: detail && detail.blockId != null ? detail.blockId : null });
        }

        function onRefresh(root, detail) {
            fanOut('refresh', root, detail);
            busEmit(EVENTS.REFRESH, {});
        }

        function onDestroy(root, detail) {
            // The runtime has already released every ctx.on() subscription for
            // this element (WeakMap-backed cleanupElement) before we get here.
            fanOut('destroy', root, detail);
            if (detail && detail.blockId != null) {
                busEmit(EVENTS.DESTROY, { blockId: detail.blockId });
            }
        }

        lifecycleOff = runtime.register(LIFECYCLE_ID, {
            mount: onMount,
            update: onUpdate,
            refresh: onRefresh,
            destroy: onDestroy,
        });

        // -------------------------------------------------------------- public

        return Object.freeze({
            version: VERSION,
            EVENTS: EVENTS,
            RUNTIME_EVENTS: RUNTIME_EVENTS,
            EVENT_PATTERN: EVENT_PATTERN,
            BaseBlock: BaseBlock,
            defineBlock: defineBlock,
            getBlockType: getBlockType,
            assetsFor: assetsFor,
            scan: scan,
            trackStyle: trackStyle,
            unregisterAll: function () {
                Array.from(definitions.keys()).forEach(function (type) {
                    var definition = definitions.get(type);
                    Array.from(instances.get(type) || []).forEach(function (instance) {
                        releaseInstance(instance, definition, { reason: 'unregisterAll' });
                    });
                    definition.unregisterBlock();
                    definitions.delete(type);
                });
                instances.clear();
                if (typeof lifecycleOff === 'function') lifecycleOff();
                lifecycleOff = null;
            },
        });
    }

    return {
        VERSION: VERSION,
        EVENTS: EVENTS,
        RUNTIME_EVENTS: RUNTIME_EVENTS,
        EVENT_PATTERN: EVENT_PATTERN,
        BaseBlock: BaseBlock,
        create: create,
    };
}));
