<?php
/**
 * Phase 3C / Milestone 3 — browser-side interaction dispatcher.
 *
 * Delegated event handling (click/submit) with server-revalidated targets.
 * Zero inline handlers, zero eval, zero new Function — only CustomEvent
 * emission for the host to consume (Alpine/Livewire/Vue/etc.).
 */

$nonce = $nonce ?? ''; // CSP nonce injected by host layout
?>
<script nonce="<?= $nonce ?>">
(function () {
    'use strict';

    /**
     * Emits a revalidated interaction event for the host application.
     * The host (customer-site, builder preview, etc.) listens for
     * 'theme:interaction' and decides how to execute the side effect.
     */
    function emitInteraction(eventType, targetId, payload, actionTrigger) {
        var detail = {
            event_type: eventType,
            target_id: targetId,
            payload: payload,
            action_trigger: actionTrigger,
        };
        window.dispatchEvent(new CustomEvent('theme:interaction', {
            detail: detail,
            bubbles: true,
            composed: true,
        }));
    }

    /**
     * Resolves a policy-accepted element target. InteractionPolicy (and the
     * mirrored isAllowedTarget regex below) accept BOTH the '#id' selector
     * form and the bare 'id' form, but querySelector only ever resolves the
     * hash form — a bare id would pass validation and then silently no-op.
     * Resolve bare ids through getElementById so every accepted target form
     * actually executes.
     */
    function resolveElement(target) {
        return target.charCodeAt(0) === 35
            ? document.querySelector(target)
            : document.getElementById(target);
    }

    /**
     * Executes the side effect for an authorized interaction.
     * Uses fetch with redirect: 'error' to prevent open redirects.
     */
    function executeInteraction(eventType, targetId, payload, actionTrigger) {
        var target = payload.target;
        if (!target) {
            return;
        }

        switch (actionTrigger) {
            case 'navigate':
                window.location.href = target;
                break;
            case 'call_api':
                // Server has already authorized the host; client fetch mirrors
                // the same allowlist logic for defense in depth.
                fetch(target, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload.values || {}),
                    credentials: 'same-origin',
                    redirect: 'error',
                }).catch(function () {
                    // Host listens for theme:interaction and shows UI feedback
                });
                break;
            case 'scroll_to':
                var el = resolveElement(target);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                break;
            case 'toggle_element':
                var el = resolveElement(target);
                if (el) {
                    el.hidden = !el.hidden;
                }
                break;
            case 'open_modal':
                var el = resolveElement(target);
                if (el && typeof el.showModal === 'function') {
                    el.showModal();
                }
                break;
        }
    }

    /**
     * Client-side host allowlists — the browser-side mirror of
     * InteractionPolicy's server-side interactionHosts/apiHosts. Hosts
     * inject them via window.ThemeInteractionHosts (e.g. from
     * config('theme-runtime.interaction_hosts') and
     * config('theme-runtime.interaction_api_hosts')). The SERVER remains
     * the authority and revalidates every event; this mirror is client-side
     * defense in depth so a widget can never drive THIS browser to an
     * un-vetted external host. Fail-closed: an empty allowlist blocks all
     * external https navigation/API calls (same-origin relative paths are
     * unaffected).
     */
    var API_HOSTS = (window.ThemeInteractionHosts && window.ThemeInteractionHosts.api) || [];
    var NAV_HOSTS = (window.ThemeInteractionHosts && window.ThemeInteractionHosts.navigation) || [];

    function isAllowlistedHost(url, hosts) {
        var host;
        try {
            host = new URL(url).hostname.toLowerCase().replace(/\.+$/, '');
        } catch (_) {
            return false;
        }
        for (var i = 0; i < hosts.length; i++) {
            if (String(hosts[i]).toLowerCase() === host) {
                return true;
            }
        }
        return false;
    }

    function warnBlocked(actionTrigger, target, reason) {
        try {
            console.warn('[theme-interaction] blocked ' + actionTrigger + ' to "' + target + '" (' + reason + ')');
        } catch (_) {}
    }

    /**
     * Validates a target locally before emission — server revalidates anyway.
     * Mirrors InteractionPolicy logic: only allowlisted action triggers and
     * safe target formats pass.
     */
    function isAllowedTarget(actionTrigger, target) {
        if (typeof target !== 'string' || target === '') {
            return false;
        }
        // Basic control-char check
        if (/[\x00-\x1F\\]/.test(target)) {
            return false;
        }

        switch (actionTrigger) {
            case 'navigate':
                // Relative path (same-origin) — no host allowlist needed
                if (/^\/(?!\/)[\w.~!$&'()*+,;=:@%/?#-]*$/.test(target)) {
                    return true;
                }
                // Absolute HTTPS — must ALSO be on the client-side navigation
                // allowlist (mirror of the server's InteractionPolicy
                // interactionHosts); fail-closed when unset.
                try {
                    var u = new URL(target);
                    if (u.protocol !== 'https:' || u.username || u.password) {
                        return false;
                    }
                    if (!isAllowlistedHost(target, NAV_HOSTS)) {
                        warnBlocked(actionTrigger, target, 'host not in interaction_hosts allowlist');
                        return false;
                    }
                    return true;
                } catch (_) {
                    return false;
                }

            case 'call_api':
                try {
                    var u = new URL(target);
                    if (u.protocol !== 'https:' || u.username || u.password) {
                        return false;
                    }
                    if (!isAllowlistedHost(target, API_HOSTS)) {
                        warnBlocked(actionTrigger, target, 'host not in interaction_api_hosts allowlist');
                        return false;
                    }
                    return true;
                } catch (_) {
                    return false;
                }

            case 'toggle_element':
            case 'open_modal':
            case 'scroll_to':
                // CSS selector: #id or bare id
                return /^#?[A-Za-z][\w:\-]{0,127}$/.test(target);

            default:
                return false;
        }
    }

    /**
     * Handles a click on an element with data-tb-interaction.
     */
    function handleClick(event) {
        var el = event.target.closest('[data-tb-interaction]');
        if (!el || el.hasAttribute('data-tb-interaction-blocked')) {
            return;
        }
        var raw = el.getAttribute('data-tb-interaction');
        if (!raw) {
            return;
        }
        var data;
        try {
            data = JSON.parse(raw);
        } catch (_) {
            return;
        }
        var actionTrigger = data.action_trigger;
        var target = data.payload?.target;
        if (!actionTrigger || !target || !isAllowedTarget(actionTrigger, target)) {
            // Blocked locally — server will also block, but fail fast for UX
            return;
        }
        emitInteraction(data.event_type, data.target_id, data.payload, actionTrigger);
        executeInteraction(data.event_type, data.target_id, data.payload, actionTrigger);
    }

    /**
     * Handles a form submit on an element with data-tb-interaction.
     */
    function handleSubmit(event) {
        var form = event.target.closest('[data-tb-interaction]');
        if (!form || form.hasAttribute('data-tb-interaction-blocked')) {
            return;
        }
        var raw = form.getAttribute('data-tb-interaction');
        if (!raw) {
            return;
        }
        var data;
        try {
            data = JSON.parse(raw);
        } catch (_) {
            return;
        }
        var actionTrigger = data.action_trigger;
        var target = data.payload?.target;
        if (!actionTrigger || !target || !isAllowedTarget(actionTrigger, target)) {
            return;
        }
        // Prevent default navigation — host handles via theme:interaction
        event.preventDefault();
        // Enrich payload with form values
        var formData = new FormData(form);
        var values = {};
        formData.forEach(function (value, key) {
            values[key] = value;
        });
        var enrichedPayload = Object.assign({}, data.payload, { values: values });
        emitInteraction(data.event_type, data.target_id, enrichedPayload, actionTrigger);
        executeInteraction(data.event_type, data.target_id, enrichedPayload, actionTrigger);
    }

    // Delegated listeners — single attachment, works for dynamic content
    document.addEventListener('click', handleClick, { capture: true });
    document.addEventListener('submit', handleSubmit, { capture: true });

    // Expose dispatcher for host integration (optional)
    window.ThemeInteractionDispatcher = {
        emit: emitInteraction,
        isAllowedTarget: isAllowedTarget,
        allowlists: { api: API_HOSTS, navigation: NAV_HOSTS },
    };
})();
</script>