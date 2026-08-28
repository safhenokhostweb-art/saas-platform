@if(config('payment-connection-warning.enabled', true))
<dialog
    data-payment-connection-dialog
    dir="rtl"
    aria-labelledby="payment-connection-warning-title"
    aria-describedby="payment-connection-warning-description"
    class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-(--ui-radius-2xl) border border-(--ui-border) bg-(--ui-surface) p-0 text-right text-(--ui-text-primary) shadow-modal backdrop:bg-slate-950/55"
>
    <div class="p-5 sm:p-6">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-(--ui-warning-bg) text-(--ui-warning)" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.8 2.5 17.3A2 2 0 0 0 4.2 20h15.6a2 2 0 0 0 1.7-2.7L13.7 3.8a2 2 0 0 0-3.4 0Z"/>
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <h2 id="payment-connection-warning-title" class="text-base font-bold sm:text-lg">پیش از پرداخت</h2>
                <p id="payment-connection-warning-description" class="mt-2 text-sm leading-7 text-(--ui-text-secondary)">
                    {{ config('payment-connection-warning.message') }}
                </p>
                <p data-payment-connection-status class="mt-3 hidden rounded-(--ui-radius-lg) bg-(--ui-warning-bg) px-3 py-2 text-xs leading-6 text-(--ui-warning-text)" role="status" aria-live="polite"></p>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
            <x-core-ui::button type="button" variant="secondary" data-payment-connection-recheck>
                {{ config('payment-connection-warning.recheck_button') }}
            </x-core-ui::button>
            <x-core-ui::button type="button" variant="primary" data-payment-connection-continue>
                {{ config('payment-connection-warning.continue_button') }}
            </x-core-ui::button>
        </div>
    </div>
</dialog>

<script>
(() => {
    if (window.__paymentConnectionWarningInstalled) return;
    window.__paymentConnectionWarningInstalled = true;

    const dialog = document.querySelector('[data-payment-connection-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const endpoint = @json(route('payment-connection.preflight'));
    const csrf = @json(csrf_token());
    const stillForeign = @json(config('payment-connection-warning.still_foreign_message'));
    const status = dialog.querySelector('[data-payment-connection-status]');
    const recheck = dialog.querySelector('[data-payment-connection-recheck]');
    const continueButton = dialog.querySelector('[data-payment-connection-continue]');
    let pending = null;
    let busy = false;

    const setBusy = (value) => {
        busy = value;
        recheck.disabled = value;
        continueButton.disabled = value;
        dialog.setAttribute('aria-busy', value ? 'true' : 'false');
    };

    const preflight = async (override = false) => {
        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ override }),
        });
        if (!response.ok) throw new Error('preflight_unavailable');
        return response.json();
    };

    const proceed = () => {
        if (!pending) return;
        const action = pending;
        pending = null;
        if (dialog.open) dialog.close('proceed');

        if (action.kind === 'link') {
            window.location.assign(action.element.href);
            return;
        }

        action.element.dataset.paymentPreflightApproved = '1';
        // requestSubmit dispatches the approved submit event synchronously. Keep
        // the original submitter enabled until that event has captured its value.
        action.element.requestSubmit(action.submitter || undefined);
        action.element.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
        });
    };

    const check = async (action) => {
        if (busy) return;
        busy = true;
        pending = action;
        try {
            const result = await preflight(false);
            if (result.allowed) {
                proceed();
                return;
            }
            status.classList.add('hidden');
            dialog.showModal();
            recheck.focus();
        } catch (_) {
            // UX advisory only: network/configuration failures must never block payment.
            proceed();
            return;
        } finally {
            busy = false;
        }
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-payment-start]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        check({ kind: 'link', element: link });
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-payment-start]');
        if (!form) return;
        if (form.dataset.paymentPreflightApproved === '1') {
            delete form.dataset.paymentPreflightApproved;
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        check({ kind: 'form', element: form, submitter: event.submitter });
    }, true);

    recheck.addEventListener('click', async () => {
        if (busy || !pending) return;
        setBusy(true);
        status.classList.add('hidden');
        try {
            const result = await preflight(false);
            if (result.allowed) {
                proceed();
                return;
            }
            status.textContent = stillForeign;
            status.classList.remove('hidden');
            recheck.focus();
        } catch (_) {
            proceed();
        } finally {
            setBusy(false);
        }
    });

    continueButton.addEventListener('click', async () => {
        if (busy || !pending) return;
        setBusy(true);
        try {
            await preflight(true);
        } catch (_) {
            // Explicit user choice is sufficient; advisory service remains fail-open.
        }
        proceed();
    });

    dialog.addEventListener('cancel', () => {
        pending = null;
        busy = false;
    });
})();
</script>
@endif
