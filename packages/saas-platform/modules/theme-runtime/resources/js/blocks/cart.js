/*
 * Phase 1/2 (ADR-067) — the live cart behaviour shared by the header cart
 * icon (mini-cart drawer) and product cards (instant add-to-cart). Inlined
 * once per render by BlockRenderer::cartAssets(), only when the host gave
 * cart endpoints; the Builder preview never loads it.
 *
 * Host contract (customer-site ShopController, all JSON with Accept: json):
 *   GET  mini   → { count, subtotal_html, items: [{ key, title, image, quantity, total_html }] }
 *   POST add    product_id, quantity[, product_variant_id] → { ok, message, cart } | 422 { ok:false, message[, url] }
 *   POST remove line_key → { ok, message, cart }
 * Endpoints come from the nearest/first [data-tb-cart] element.
 *
 * Pure helpers are exposed on window.TbCart for the Node tests.
 */
(function (root) {
  'use strict';
  if (root.TbCart && root.TbCart.version === 1) return;

  const SAFE_URL = /^(https?:\/\/|\/)/i;

  function faNumber(n) {
    return Number(n || 0).toLocaleString('fa-IR');
  }

  /** «۳ کالا» / «سبد خالی است». */
  function countLabel(count) {
    const n = Math.max(0, Math.floor(Number(count) || 0));
    return n > 0 ? faNumber(n) + ' کالا' : 'سبد خالی است';
  }

  /*
   * The mini-cart payload made safe to render: titles stay text, images
   * must be http(s)/root-relative, and the money HTML may only carry digits,
   * Persian separators, the Toman word and the host's faded-zeros span.
   */
  const MONEY_HTML = /^[\s۰-۹0-9٬٫,.٬٫تومانریال]*(<span class="currency-faded-zeros"[^<>]*>[۰-۹0-9]*<\/span>)?[\s۰-۹0-9٬٫,.٬٫تومانریال]*$/;
  function money(html) {
    const s = typeof html === 'string' ? html : '';
    return MONEY_HTML.test(s) ? s : '';
  }
  function normalizeMini(data) {
    const d = data && typeof data === 'object' ? data : {};
    const items = (Array.isArray(d.items) ? d.items : []).filter((it) => it && typeof it.key === 'string' && typeof it.title === 'string').map((it) => ({
      key: it.key,
      title: it.title,
      image: typeof it.image === 'string' && SAFE_URL.test(it.image) ? it.image : '',
      quantity: Math.max(1, Math.floor(Number(it.quantity) || 1)),
      totalHtml: money(it.total_html),
    }));
    return { count: Math.max(0, Math.floor(Number(d.count) || 0)), subtotalHtml: money(d.subtotal_html), items };
  }

  const api = { version: 1, countLabel, normalizeMini, money, faNumber };
  root.TbCart = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;

  const document = root.document;
  if (!document || typeof document.addEventListener !== 'function') return;

  function config(from) {
    const el = (from && from.closest && from.closest('[data-tb-cart]')) || document.querySelector('[data-tb-cart]');
    try {
      const c = JSON.parse(el ? el.getAttribute('data-tb-cart') : '{}');
      return c && typeof c === 'object' ? c : {};
    } catch (_) {
      return {};
    }
  }

  function csrfHeaders() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta && meta.content ? { 'X-CSRF-TOKEN': meta.content } : {};
  }

  function request(url, body) {
    return fetch(url, {
      method: body ? 'POST' : 'GET',
      body: body || undefined,
      credentials: 'same-origin',
      headers: Object.assign({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body ? csrfHeaders() : {}),
    }).then((res) => res.json().catch(() => ({})).then((json) => ({ status: res.status, json })));
  }

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
    setTimeout(() => { el.classList.remove('is-visible'); setTimeout(() => el.remove(), 300); }, 4000);
  }

  function setBadges(count) {
    document.querySelectorAll('[data-tb-cart-count]').forEach((b) => {
      b.textContent = faNumber(count);
      b.hidden = count <= 0;
    });
    document.querySelectorAll('[data-tb-cart-open]').forEach((t) => t.setAttribute('aria-label', 'سبد خرید، ' + countLabel(count)));
  }

  /* ── Drawer ──────────────────────────────────────────────────────── */
  const X_SVG = '<svg class="tb-icon-svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
  const TRASH_SVG = '<svg class="tb-icon-svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>';
  let drawer = null;
  let opener = null;
  let cfg = {};

  function build() {
    const el = document.createElement('div');
    el.className = 'tb-cart-drawer';
    el.hidden = true;
    el.innerHTML = '<div class="tb-cart-drawer__backdrop" data-tb-cart-close></div>'
      + '<aside class="tb-cart-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="tb-cart-drawer-title">'
      + '<div class="tb-cart-drawer__head"><h2 id="tb-cart-drawer-title">سبد خرید</h2><span class="tb-cart-drawer__count"></span>'
      + '<button type="button" class="tb-cart-drawer__close" data-tb-cart-close aria-label="بستن سبد خرید">' + X_SVG + '</button></div>'
      + '<div class="tb-cart-drawer__body" aria-live="polite"></div>'
      + '<div class="tb-cart-drawer__foot" hidden><div class="tb-cart-drawer__subtotal"><span>جمع کل</span><strong></strong></div>'
      + '<a class="tb-btn tb-btn-primary tb-cart-drawer__checkout">مشاهدهٔ سبد و تکمیل خرید</a>'
      + '<button type="button" class="tb-cart-drawer__continue" data-tb-cart-close>ادامهٔ خرید</button></div>'
      + '</aside>';
    document.body.appendChild(el);
    el.addEventListener('click', (e) => {
      if (e.target.closest('[data-tb-cart-close]')) close();
      const rm = e.target.closest('[data-tb-cart-remove]');
      if (rm) remove(rm.getAttribute('data-tb-cart-remove'), rm);
    });
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { e.preventDefault(); close(); return; }
      if (e.key !== 'Tab') return;
      const f = Array.from(el.querySelectorAll('button, a[href]')).filter((n) => n.offsetParent !== null);
      if (!f.length) return;
      if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    });
    return el;
  }

  function status(text) {
    drawer.querySelector('.tb-cart-drawer__body').innerHTML = '<p class="tb-cart-drawer__status"></p>';
    drawer.querySelector('.tb-cart-drawer__status').textContent = text;
  }

  function render(raw) {
    const data = normalizeMini(raw);
    setBadges(data.count);
    if (!drawer) return;
    drawer.querySelector('.tb-cart-drawer__count').textContent = countLabel(data.count);
    const foot = drawer.querySelector('.tb-cart-drawer__foot');
    const body = drawer.querySelector('.tb-cart-drawer__body');
    if (!data.items.length) {
      foot.hidden = true;
      body.innerHTML = '<div class="tb-cart-drawer__empty"><p>سبد خرید شما خالی است.</p></div>';
      return;
    }
    const list = document.createElement('ul');
    list.className = 'tb-cart-drawer__items';
    data.items.forEach((it) => {
      const li = document.createElement('li');
      li.className = 'tb-cart-drawer__item';
      const media = document.createElement('span');
      media.className = 'tb-cart-drawer__media';
      if (it.image) {
        const img = document.createElement('img');
        img.src = it.image;
        img.alt = '';
        img.loading = 'lazy';
        media.appendChild(img);
      }
      const text = document.createElement('span');
      text.className = 'tb-cart-drawer__text';
      const title = document.createElement('span');
      title.className = 'tb-cart-drawer__title';
      title.textContent = it.title;
      const meta = document.createElement('span');
      meta.className = 'tb-cart-drawer__meta';
      meta.innerHTML = faNumber(it.quantity) + ' × <span class="tb-cart-drawer__price"></span>';
      meta.querySelector('.tb-cart-drawer__price').innerHTML = it.totalHtml; // money() allowlisted
      text.append(title, meta);
      const rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'tb-cart-drawer__remove';
      rm.setAttribute('data-tb-cart-remove', it.key);
      rm.setAttribute('aria-label', 'حذف «' + it.title + '» از سبد');
      rm.innerHTML = TRASH_SVG;
      li.append(media, text, rm);
      list.appendChild(li);
    });
    body.innerHTML = '';
    body.appendChild(list);
    foot.hidden = false;
    foot.querySelector('.tb-cart-drawer__subtotal strong').innerHTML = data.subtotalHtml;
    foot.querySelector('.tb-cart-drawer__checkout').href = cfg.page || '#';
  }

  function refresh() {
    if (!cfg.mini) return Promise.resolve();
    return request(cfg.mini).then(({ status: s, json }) => {
      if (s >= 200 && s < 300) render(json);
      else if (drawer) status('دریافت سبد خرید ممکن نشد؛ دوباره تلاش کنید.');
    }).catch(() => drawer && status('ارتباط با سرور برقرار نشد.'));
  }

  function open(from) {
    cfg = config(from);
    if (!cfg.mini) return false;
    drawer = drawer || build();
    opener = document.activeElement;
    drawer.hidden = false;
    document.documentElement.classList.add('tb-cart-open');
    requestAnimationFrame(() => drawer.classList.add('is-open'));
    status('در حال بارگذاری سبد…');
    drawer.querySelector('.tb-cart-drawer__close').focus();
    refresh();
    return true;
  }

  function close() {
    if (!drawer || drawer.hidden) return;
    drawer.classList.remove('is-open');
    drawer.hidden = true;
    document.documentElement.classList.remove('tb-cart-open');
    if (opener && typeof opener.focus === 'function' && document.contains(opener)) opener.focus({ preventScroll: true });
  }

  function remove(key, button) {
    if (!cfg.remove) return;
    const fd = new FormData();
    fd.set('line_key', key);
    button.disabled = true;
    request(cfg.remove, fd).then(({ status: s, json }) => {
      if (s >= 200 && s < 300 && json.cart) {
        render(json.cart);
        toast(json.message || 'محصول از سبد حذف شد.', 'success');
      } else {
        button.disabled = false;
        toast((json && json.message) || 'حذف از سبد ممکن نشد.', 'error');
      }
    }).catch(() => { button.disabled = false; toast('ارتباط با سرور برقرار نشد.', 'error'); });
  }

  /* ── Add to cart ─────────────────────────────────────────────────── */
  function add(button) {
    const c = config(button);
    if (!c.add || button.getAttribute('aria-busy') === 'true') return;
    const fd = new FormData();
    fd.set('product_id', button.getAttribute('data-product-id') || '');
    fd.set('quantity', button.getAttribute('data-quantity') || '1');
    const variant = button.getAttribute('data-variant-id');
    if (variant) fd.set('product_variant_id', variant);
    const label = button.innerHTML;
    button.setAttribute('aria-busy', 'true');
    button.disabled = true;
    button.classList.add('is-busy');
    request(c.add, fd).then(({ status: s, json }) => {
      if (s >= 200 && s < 300 && json.ok !== false) {
        if (json.cart) render(json.cart);
        button.classList.add('is-added');
        toast(json.message || 'به سبد اضافه شد.', 'success');
        if (button.hasAttribute('data-open-cart')) open(button);
      } else if (json && json.url && SAFE_URL.test(json.url)) {
        location.assign(json.url); // e.g. a variant must be chosen on the product page
      } else {
        toast((json && json.message) || (s === 429 ? 'تعداد درخواست‌ها زیاد است؛ کمی بعد تلاش کنید.' : 'افزودن به سبد ممکن نشد.'), 'error');
      }
    }).catch(() => toast('ارتباط با سرور برقرار نشد.', 'error')).finally(() => {
      button.removeAttribute('aria-busy');
      button.disabled = false;
      button.classList.remove('is-busy');
      setTimeout(() => { button.classList.remove('is-added'); button.innerHTML = label; }, 1800);
    });
  }

  document.addEventListener('click', (e) => {
    const opener_ = e.target.closest && e.target.closest('[data-tb-cart-open]');
    if (opener_ && open(opener_)) { e.preventDefault(); return; }
    const adder = e.target.closest && e.target.closest('[data-tb-add-to-cart]');
    if (adder) { e.preventDefault(); add(adder); }
  });

  Object.assign(api, { open, close, refresh, add });
})(typeof window !== 'undefined' ? window : globalThis);
