/*
 * Phase 0.7 (ADR-066) — the live behaviour of the theme runtime's site
 * forms: newsletter subscription and site search. The renderer inlines this
 * once per page, and only when the host gave it real endpoints
 * (BlockRenderer::siteEndpoints()); the Builder preview never loads it.
 * Event delegation on document, so blocks rendered later work too.
 *
 *  - form[data-tb-newsletter]: validated (email or Iranian mobile), sent once
 *    with fetch + the page's CSRF token, answered with a Persian toast and
 *    an inline status line.
 *  - form[data-tb-search] / [data-tb-search-open]: opens one search dialog —
 *    a centred modal on desktop, full screen on phones — with live,
 *    debounced suggestions when the host has a suggest endpoint.
 *
 * Pure helpers are exposed on window.TbSiteForms for the Node tests.
 */
(function (root) {
  'use strict';
  if (root.TbSiteForms && root.TbSiteForms.version === 1) return;

  const PERSIAN = '۰۱۲۳۴۵۶۷۸۹';
  const ARABIC = '٠١٢٣٤٥٦٧٨٩';

  function latinDigits(text) {
    return String(text == null ? '' : text).replace(/[۰-۹٠-٩]/g, (d) => {
      const p = PERSIAN.indexOf(d);
      return String(p >= 0 ? p : ARABIC.indexOf(d));
    });
  }

  /** {channel:'email'|'mobile', value} or null — same rules as the server. */
  function normalizeContact(raw) {
    const text = latinDigits(raw).trim();
    if (text === '') return null;
    if (text.includes('@')) {
      const email = text.toLowerCase();
      return email.length <= 190 && /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email) ? { channel: 'email', value: email } : null;
    }
    const digits = text.replace(/[\s\-().]/g, '');
    const m = digits.match(/^(?:\+98|0098|98|0)?(9\d{9})$/);
    return m ? { channel: 'mobile', value: '0' + m[1] } : null;
  }

  const MESSAGES = {
    empty: 'لطفاً ایمیل یا شماره موبایل خود را وارد کنید.',
    invalid: 'ایمیل یا شماره موبایل معتبر نیست. نمونه: name@example.com یا ۰۹۱۲۳۴۵۶۷۸۹',
    busy: 'در حال ثبت…',
    throttled: 'تعداد درخواست‌ها زیاد است؛ لطفاً یک دقیقهٔ دیگر دوباره تلاش کنید.',
    expired: 'نشست شما منقضی شده است؛ صفحه را تازه کنید و دوباره تلاش کنید.',
    network: 'ارتباط با سرور برقرار نشد؛ اتصال اینترنت را بررسی کنید.',
    failed: 'ثبت انجام نشد؛ لطفاً دوباره تلاش کنید.',
    subscribed: 'عضویت شما در خبرنامه ثبت شد. سپاس!',
    already: 'این ایمیل یا شماره قبلاً در خبرنامه ثبت شده است.',
  };

  /** The Persian message for a fetch outcome (status + parsed JSON body). */
  function newsletterMessage(status, body) {
    const b = body && typeof body === 'object' ? body : {};
    if (status === 0) return { ok: false, text: MESSAGES.network };
    if (status === 419) return { ok: false, text: MESSAGES.expired };
    if (status === 429) return { ok: false, text: MESSAGES.throttled };
    if (status === 422) {
      const err = b.errors && (b.errors.contact || b.errors[Object.keys(b.errors)[0]]);
      return { ok: false, text: (Array.isArray(err) ? err[0] : err) || MESSAGES.invalid };
    }
    if (status >= 200 && status < 300 && b.ok !== false) {
      return { ok: true, text: b.message || (b.status === 'already' ? MESSAGES.already : MESSAGES.subscribed), already: b.status === 'already' };
    }
    return { ok: false, text: b.message || MESSAGES.failed };
  }

  function formatToman(amount) {
    const n = Number(amount);
    if (!Number.isFinite(n) || n <= 0) return '';
    return Math.round(n).toLocaleString('fa-IR').replace(/,/g, '٬') + ' تومان';
  }

  /** Suggestion items for one scope ('all' | 'products' | 'posts'). */
  function filterSuggestions(items, scope) {
    const list = Array.isArray(items) ? items : [];
    const want = scope === 'products' ? 'product' : scope === 'posts' ? 'post' : null;
    return list.filter((it) => it && typeof it.title === 'string' && typeof it.url === 'string'
      && /^(https?:\/\/|\/)/i.test(it.url) && (!want || it.type === want));
  }

  /** The results-page URL for a query and scope route ({action, params}). */
  function resultsUrl(route, query, base) {
    if (!route || typeof route.action !== 'string') return '';
    const url = new URL(route.action, base);
    Object.entries(route.params || {}).forEach(([k, v]) => url.searchParams.set(k, String(v)));
    url.searchParams.set(route.param || 'q', String(query || '').trim());
    return url.toString();
  }

  function debounce(fn, ms) {
    let t = null;
    const wrapped = function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), ms);
    };
    wrapped.cancel = () => clearTimeout(t);
    return wrapped;
  }

  const api = { version: 1, latinDigits, normalizeContact, newsletterMessage, formatToman, filterSuggestions, resultsUrl, debounce, MESSAGES };
  root.TbSiteForms = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;

  const document = root.document;
  if (!document || typeof document.addEventListener !== 'function') return;

  /* ── Toasts ─────────────────────────────────────────────────────── */
  function toast(text, kind) {
    let region = document.querySelector('.tb-toast-region');
    if (!region) {
      region = document.createElement('div');
      region.className = 'tb-toast-region';
      region.setAttribute('role', 'status');
      region.setAttribute('aria-live', 'polite');
      document.body.appendChild(region);
    }
    const el = document.createElement('div');
    el.className = 'tb-toast tb-toast--' + (kind === 'error' ? 'error' : 'success');
    el.textContent = text;
    region.appendChild(el);
    requestAnimationFrame(() => el.classList.add('is-visible'));
    setTimeout(() => {
      el.classList.remove('is-visible');
      setTimeout(() => el.remove(), 300);
    }, 4500);
  }

  function csrfHeaders() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return { 'X-CSRF-TOKEN': meta.content };
    const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    return cookie ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice(11)) } : {};
  }

  /* ── Newsletter ─────────────────────────────────────────────────── */
  function setStatus(form, text, ok) {
    const status = form.querySelector('.tb-newsletter-status');
    if (status) {
      status.textContent = text;
      status.classList.toggle('is-error', !ok);
    }
    const input = form.querySelector('[name="contact"]');
    if (input) input.setAttribute('aria-invalid', ok ? 'false' : 'true');
  }

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-tb-newsletter')) return;
    event.preventDefault();
    if (form.dataset.tbBusy === '1') return;
    const now = Date.now();
    if (now - Number(form.dataset.tbLast || 0) < 1000) return; // double click / Enter repeat
    form.dataset.tbLast = String(now);

    const input = form.querySelector('[name="contact"]');
    const raw = input ? input.value : '';
    const contact = normalizeContact(raw);
    form.dataset.tbTried = '1';
    if (!contact) {
      const text = raw.trim() === '' ? MESSAGES.empty : MESSAGES.invalid;
      setStatus(form, text, false);
      toast(text, 'error');
      if (input) input.focus();
      return;
    }

    const button = form.querySelector('button[type="submit"]');
    form.dataset.tbBusy = '1';
    form.setAttribute('aria-busy', 'true');
    if (button) button.disabled = true;
    setStatus(form, MESSAGES.busy, true);

    const data = new FormData(form);
    data.set('contact', contact.value);
    fetch(form.action, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: Object.assign({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, csrfHeaders()),
    })
      .then((res) => res.json().catch(() => ({})).then((body) => newsletterMessage(res.status, body)))
      .catch(() => newsletterMessage(0, null))
      .then((result) => {
        setStatus(form, result.text, result.ok);
        toast(result.text, result.ok ? 'success' : 'error');
        if (result.ok) {
          form.reset();
          delete form.dataset.tbTried;
        }
      })
      .finally(() => {
        delete form.dataset.tbBusy;
        form.removeAttribute('aria-busy');
        if (button) button.disabled = false;
      });
  });

  // After a failed attempt, re-check as the visitor types (debounced).
  const revalidate = debounce((form) => {
    const input = form.querySelector('[name="contact"]');
    if (!input || form.dataset.tbBusy === '1') return;
    if (normalizeContact(input.value)) setStatus(form, '', true);
  }, 350);
  document.addEventListener('input', (event) => {
    const form = event.target && event.target.form;
    if (form && form.hasAttribute('data-tb-newsletter') && form.dataset.tbTried === '1') revalidate(form);
  });

  /* ── Search dialog ──────────────────────────────────────────────── */
  const SCOPE_LABELS = { all: 'همه', products: 'محصولات', posts: 'مطالب' };
  const TYPE_LABELS = { product: 'محصول', post: 'مطلب' };
  const SEARCH_SVG = '<svg class="tb-icon-svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';
  // Catalog bodies (IconLibrary `package`, `file-text`) for a suggestion without a photo.
  const TYPE_ICONS = {
    product: '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7"/><path d="m7.5 4.27 9 5.15"/>',
    post: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
  };
  let dialog = null;
  let state = null;
  let reopenBlockedUntil = 0; // focus returning to the page field must not reopen

  function routesOf(form) {
    try {
      const routes = JSON.parse(form.getAttribute('data-tb-search') || '{}');
      return routes && typeof routes === 'object' ? routes : {};
    } catch (_) {
      return {};
    }
  }

  function buildDialog() {
    const el = document.createElement('div');
    el.className = 'tb-search-overlay';
    el.hidden = true;
    el.innerHTML = '<div class="tb-search-overlay__backdrop" data-tb-search-close></div>'
      + '<div class="tb-search-overlay__panel" role="dialog" aria-modal="true" aria-label="جستجو در سایت">'
      + '<form class="tb-search-overlay__form" role="search" method="get">'
      + '<span class="tb-search-overlay__icon">' + SEARCH_SVG + '</span>'
      + '<input class="tb-search-overlay__input" type="search" autocomplete="off" enterkeyhint="search" aria-label="عبارت جستجو" aria-autocomplete="list" aria-controls="tb-search-overlay-results">'
      + '<button type="button" class="tb-search-overlay__close" data-tb-search-close aria-label="بستن جستجو">بستن</button>'
      + '</form>'
      + '<div class="tb-search-overlay__scopes" role="group" aria-label="محدودهٔ جستجو"></div>'
      + '<div class="tb-search-overlay__body">'
      + '<p class="tb-search-overlay__status" aria-live="polite"></p>'
      + '<ul class="tb-search-overlay__results" id="tb-search-overlay-results" role="listbox" aria-label="پیشنهادها"></ul>'
      + '</div>'
      + '<a class="tb-search-overlay__all" hidden></a>'
      + '</div>';
    document.body.appendChild(el);

    const input = el.querySelector('.tb-search-overlay__input');
    el.querySelector('form').addEventListener('submit', (e) => {
      e.preventDefault();
      const q = input.value.trim();
      if (q === '') return input.focus();
      const url = resultsUrl(state.routes[state.scope], q, location.href);
      if (url) location.assign(url);
    });
    input.addEventListener('input', () => { updateAll(); suggest(); });
    el.addEventListener('click', (e) => {
      if (e.target.closest('[data-tb-search-close]')) close();
      const scopeBtn = e.target.closest('[data-tb-scope]');
      if (scopeBtn) {
        state.scope = scopeBtn.getAttribute('data-tb-scope');
        renderScopes();
        updateAll();
        suggest.cancel();
        runSuggest();
        input.focus();
      }
    });
    el.addEventListener('keydown', onKeydown);
    return el;
  }

  function renderScopes() {
    const box = dialog.querySelector('.tb-search-overlay__scopes');
    const scopes = Object.keys(SCOPE_LABELS).filter((s) => state.routes[s]);
    box.hidden = scopes.length < 2;
    box.innerHTML = '';
    scopes.forEach((s) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'tb-search-overlay__scope';
      b.setAttribute('data-tb-scope', s);
      b.setAttribute('aria-pressed', s === state.scope ? 'true' : 'false');
      b.textContent = SCOPE_LABELS[s];
      box.appendChild(b);
    });
  }

  function updateAll() {
    const q = dialog.querySelector('.tb-search-overlay__input').value.trim();
    const link = dialog.querySelector('.tb-search-overlay__all');
    const url = q ? resultsUrl(state.routes[state.scope], q, location.href) : '';
    link.hidden = !url;
    if (url) {
      link.href = url;
      link.textContent = 'مشاهدهٔ همهٔ نتایج برای «' + q + '»';
    }
  }

  function setSearchStatus(text) {
    dialog.querySelector('.tb-search-overlay__status').textContent = text;
  }

  function runSuggest() {
    const list = dialog.querySelector('.tb-search-overlay__results');
    const q = dialog.querySelector('.tb-search-overlay__input').value.trim();
    if (state.controller) state.controller.abort();
    list.innerHTML = '';
    if (!state.suggest) return setSearchStatus(q ? 'برای دیدن نتایج، Enter را بزنید.' : 'عبارت جستجو را بنویسید و Enter را بزنید.');
    if (q.length < 2) return setSearchStatus(q ? 'حداقل دو حرف بنویسید…' : 'نام محصول یا موضوع مطلب را بنویسید.');
    setSearchStatus('در حال جستجو…');
    const controller = typeof AbortController === 'function' ? new AbortController() : null;
    state.controller = controller;
    const url = new URL(state.suggest, location.href);
    url.searchParams.set('q', q);
    fetch(url.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller ? controller.signal : undefined })
      .then((res) => (res.ok ? res.json() : Promise.reject(res.status)))
      .then((body) => {
        const items = filterSuggestions(body && body.items, state.scope);
        if (!items.length) return setSearchStatus('نتیجه‌ای برای «' + q + '» پیدا نشد.');
        setSearchStatus(items.length.toLocaleString('fa-IR') + ' پیشنهاد');
        items.forEach((it, i) => {
          const li = document.createElement('li');
          li.setAttribute('role', 'none');
          const a = document.createElement('a');
          a.className = 'tb-search-overlay__item';
          a.href = it.url;
          a.setAttribute('role', 'option');
          a.id = 'tb-search-option-' + i;
          const media = document.createElement('span');
          media.className = 'tb-search-overlay__media';
          if (typeof it.image === 'string' && /^(https?:\/\/|\/)/i.test(it.image)) {
            const img = document.createElement('img');
            img.src = it.image;
            img.alt = '';
            img.loading = 'lazy';
            media.appendChild(img);
          } else {
            media.innerHTML = TYPE_ICONS[it.type] ? SEARCH_SVG.replace(/<circle[^]*<\/svg>/, TYPE_ICONS[it.type] + '</svg>') : SEARCH_SVG;
          }
          const text = document.createElement('span');
          text.className = 'tb-search-overlay__text';
          const title = document.createElement('span');
          title.className = 'tb-search-overlay__title';
          title.textContent = it.title;
          const meta = document.createElement('span');
          meta.className = 'tb-search-overlay__meta';
          meta.textContent = [TYPE_LABELS[it.type] || '', it.type === 'product' ? formatToman(it.price) : ''].filter(Boolean).join(' · ');
          text.append(title, meta);
          a.append(media, text);
          li.appendChild(a);
          list.appendChild(li);
        });
      })
      .catch((err) => {
        if (err && err.name === 'AbortError') return;
        setSearchStatus('پیشنهادها در دسترس نیست؛ برای دیدن نتایج Enter را بزنید.');
      });
  }
  const suggest = debounce(runSuggest, 250);

  function focusables() {
    return Array.from(dialog.querySelectorAll('input, button:not([hidden]), a[href]:not([hidden])'))
      .filter((el) => !el.closest('[hidden]') && el.offsetParent !== null);
  }

  function onKeydown(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      return close();
    }
    const options = Array.from(dialog.querySelectorAll('.tb-search-overlay__item'));
    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && options.length) {
      e.preventDefault();
      const i = options.indexOf(document.activeElement);
      const next = e.key === 'ArrowDown' ? (i + 1) % options.length : (i <= 0 ? options.length - 1 : i - 1);
      options[next].focus();
      return;
    }
    if (e.key === 'Tab') {
      const items = focusables();
      if (!items.length) return;
      const first = items[0];
      const last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  }

  function open(form, initial) {
    const routes = routesOf(form);
    const scopes = Object.keys(SCOPE_LABELS).filter((s) => routes[s]);
    if (!scopes.length) return false;
    dialog = dialog || buildDialog();
    const wanted = form.getAttribute('data-tb-scope') || 'all';
    state = { routes, scope: routes[wanted] ? wanted : scopes[0], suggest: form.getAttribute('data-tb-suggest') || '', opener: document.activeElement, controller: null };
    const input = dialog.querySelector('.tb-search-overlay__input');
    input.placeholder = form.querySelector('input[type="search"]')?.placeholder || 'جستجو در محصولات و مطالب…';
    input.value = initial || '';
    renderScopes();
    updateAll();
    dialog.hidden = false;
    document.documentElement.classList.add('tb-search-open');
    requestAnimationFrame(() => dialog.classList.add('is-open'));
    input.focus();
    if (input.value) runSuggest(); else setSearchStatus(state.suggest ? 'نام محصول یا موضوع مطلب را بنویسید.' : 'عبارت جستجو را بنویسید و Enter را بزنید.');
    return true;
  }

  function close() {
    if (!dialog || dialog.hidden) return;
    if (state && state.controller) state.controller.abort();
    suggest.cancel();
    dialog.classList.remove('is-open');
    dialog.hidden = true;
    document.documentElement.classList.remove('tb-search-open');
    reopenBlockedUntil = Date.now() + 400;
    const opener = state && state.opener;
    if (opener && typeof opener.focus === 'function' && document.contains(opener)) opener.focus({ preventScroll: true });
  }

  function searchFormOf(el) {
    const form = el && el.closest ? el.closest('form[data-tb-search]') : null;
    if (form) return form;
    const opener = el && el.closest ? el.closest('[data-tb-search-open]') : null;
    return opener ? document.getElementById(opener.getAttribute('data-tb-search-open')) : null;
  }

  // Typing in (or tapping) a page search field moves into the dialog.
  document.addEventListener('focusin', (e) => {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'search' || Date.now() < reopenBlockedUntil) return;
    const form = input.closest('form[data-tb-search]');
    if (form && open(form, input.value)) input.blur();
  });
  document.addEventListener('click', (e) => {
    // A click on a field that kept focus (e.g. after Esc) reopens too.
    const field = e.target instanceof HTMLInputElement && e.target.type === 'search' ? e.target.closest('form[data-tb-search]') : null;
    if (field && open(field, e.target.value)) {
      e.target.blur();
      return;
    }
    const btn = e.target.closest && e.target.closest('[data-tb-search-open]');
    if (!btn) return;
    const form = searchFormOf(btn);
    if (form && open(form, '')) e.preventDefault();
  });
  // Without the dialog (e.g. it failed to open) the form still submits as
  // a plain GET; with it, a submitted page form goes through the dialog's.
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-tb-search')) return;
    const q = (form.querySelector('input[type="search"]') || {}).value || '';
    const routes = routesOf(form);
    const scope = form.getAttribute('data-tb-scope') || 'all';
    const url = q.trim() ? resultsUrl(routes[scope] || routes.all, q, location.href) : '';
    e.preventDefault();
    if (url) location.assign(url); else open(form, '');
  });
})(typeof window !== 'undefined' ? window : globalThis);
